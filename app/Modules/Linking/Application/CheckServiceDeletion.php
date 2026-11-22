<?php
namespace App\Modules\Linking\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Linking\Infrastructure\ServiceDeletionEndpoint;
use App\Modules\Linking\Infrastructure\ServiceDeletionUnreachableException;

/**
 * 退会の前に、サービス側で削除できるかを問い合わせる。
 *
 * 退会の画面に「削除できない」を先に出して、断られる依頼を送らずに済ませるためのもの。
 * 答えないサービスは止めない (依頼のときに断られれば本人へ知らせる)。
 */
class CheckServiceDeletion {
    /** 画面の表示を待たせるので短くする */
    private const TIMEOUT_SECONDS = 3;

    private readonly ServiceDeletionEndpoint $endpoint;

    private readonly RequestServiceDeletion $requests;

    public function __construct(ServiceDeletionEndpoint $endpoint, RequestServiceDeletion $requests) {
        $this->endpoint = $endpoint;
        $this->requests = $requests;
    }

    /**
     * @param string $identityId 認証主体のID (ULID)
     * @param list<string> $serviceAccountIds 問い合わせるサービスアカウントのID
     * @return array<string, array{accepted: bool, reason: string|null, graceDays: int|null}|null> 答えなければ null
     */
    public function execute(string $identityId, array $serviceAccountIds): array {
        $serviceAccounts = ServiceAccountModel::query()
            ->whereIn('id', $serviceAccountIds)
            ->where('auth_identity_id', $identityId)
            ->get();

        $clients = OAuthClientModel::query()
            ->whereIn('id', $serviceAccounts->pluck('client_id')->all())
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($serviceAccounts as $serviceAccount) {
            $client = $clients->get($serviceAccount->client_id);
            if ($client === null || !$this->requests->supports($serviceAccount, $client)) continue;

            $result[$serviceAccount->id] = $this->ask($serviceAccount, $client);
        }

        return $result;
    }

    /**
     * @param ServiceAccountModel $serviceAccount
     * @param OAuthClientModel $client
     * @return array{accepted: bool, reason: string|null, graceDays: int|null}|null
     */
    private function ask(ServiceAccountModel $serviceAccount, OAuthClientModel $client): ?array {
        try {
            return $this->endpoint->send(
                (string) $client->deletion_request_uri,
                $client->id,
                $serviceAccount->sub,
                $serviceAccount->service_user_id,
                ServiceDeletionEndpoint::ACTION_CHECK,
                self::TIMEOUT_SECONDS,
            )->toArray();
        } catch (ServiceDeletionUnreachableException) {
            // 答えないことを理由に退会の選択肢を狭めない
            return null;
        }
    }
}
