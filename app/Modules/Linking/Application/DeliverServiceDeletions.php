<?php
namespace App\Modules\Linking\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Linking\Domain\ServiceDeletionAnswer;
use App\Modules\Linking\Domain\ServiceDeletionStatus;
use App\Modules\Linking\Infrastructure\ServiceDeletionEndpoint;
use App\Modules\Linking\Infrastructure\ServiceDeletionRequestModel;
use App\Modules\Linking\Infrastructure\ServiceDeletionUnreachableException;
use App\Modules\Linking\Mail\ServiceDeletionRefusedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 積まれた削除依頼をサービスへ送る。
 *
 * 退会の直後に1度送り、届かなかったものはスケジュールから再送する。
 * 届かないことで退会を止めはしない。
 */
class DeliverServiceDeletions {
    /** 再送を打ち切る回数。15分おきなので、おおむね1日 */
    private const MAX_ATTEMPTS = 96;

    /** 退会の画面から送るので長くは待たない */
    private const TIMEOUT_SECONDS = 5;

    private readonly ServiceDeletionEndpoint $endpoint;

    public function __construct(ServiceDeletionEndpoint $endpoint) {
        $this->endpoint = $endpoint;
    }

    /**
     * まだ届いていない依頼をすべて送る。
     *
     * @return int 送れた (受け付けられた・断られた) 件数
     */
    public function pending(): int {
        $delivered = 0;

        $requests = ServiceDeletionRequestModel::query()
            ->where('status', ServiceDeletionStatus::PENDING->value)
            ->orderBy('created_at')
            ->get();

        foreach ($requests as $request) {
            if ($this->deliver($request)) $delivered++;
        }

        return $delivered;
    }

    /**
     * @param ServiceDeletionRequestModel $request 送る依頼
     * @return bool 返答を得られたか
     */
    public function deliver(ServiceDeletionRequestModel $request): bool {
        $client = OAuthClientModel::query()->find($request->client_id);

        // 依頼を積んだあとで URL が外された。送り先が無いので打ち切る
        if ($client === null || $client->deletion_request_uri === null) {
            $request->forceFill(['status' => ServiceDeletionStatus::FAILED])->save();

            return false;
        }

        try {
            $answer = $this->endpoint->send(
                $client->deletion_request_uri,
                $client->id,
                $request->sub,
                $request->service_user_id,
                ServiceDeletionEndpoint::ACTION_DELETE,
                self::TIMEOUT_SECONDS,
            );
        } catch (ServiceDeletionUnreachableException $e) {
            $this->retryLater($request, $e);

            return false;
        }

        $this->settle($request, $client, $answer);

        return true;
    }

    /**
     * @param ServiceDeletionRequestModel $request
     * @param ServiceDeletionUnreachableException $error
     */
    private function retryLater(ServiceDeletionRequestModel $request, ServiceDeletionUnreachableException $error): void {
        $attempts = $request->attempts + 1;
        $status = $attempts >= self::MAX_ATTEMPTS ? ServiceDeletionStatus::FAILED : ServiceDeletionStatus::PENDING;

        $request->forceFill(['attempts' => $attempts, 'status' => $status])->save();

        Log::warning($error->getMessage(), ['request' => $request->id, 'attempts' => $attempts]);
    }

    /**
     * @param ServiceDeletionRequestModel $request
     * @param OAuthClientModel $client
     * @param ServiceDeletionAnswer $answer
     */
    private function settle(ServiceDeletionRequestModel $request, OAuthClientModel $client, ServiceDeletionAnswer $answer): void {
        $request->forceFill([
            'status' => $answer->accepted ? ServiceDeletionStatus::ACCEPTED : ServiceDeletionStatus::REFUSED,
            'attempts' => $request->attempts + 1,
            'reason' => $answer->reason,
            'sent_at' => now(),
        ])->save();

        if ($answer->accepted || $request->notify_email === null) return;

        Mail::to($request->notify_email)->send(new ServiceDeletionRefusedMail($client->displayName(), $answer->reason));
    }
}
