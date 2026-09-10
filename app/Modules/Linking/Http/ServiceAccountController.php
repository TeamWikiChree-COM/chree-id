<?php
namespace App\Modules\Linking\Http;

use App\Modules\Linking\Application\ClaimTickets;
use App\Modules\Linking\Application\DeactivateServiceAccount;
use App\Modules\Linking\Application\IssueServiceAccount;
use App\Modules\Registry\Http\OfficialClientGuard;
use App\Support\Api\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * サービスが自分の利用者ぶんの ChreeID を取りに来る口。
 *
 * ブラウザを介さないサーバ間通信。利用者の同意なしにアカウントが増えるので、
 * 呼べるのは公式サービスだけにしている。承認済みの第三者に開けると、
 * そのサービスの都合で ChreeID の利用者が水増しされてしまう。
 */
class ServiceAccountController {

    private readonly OfficialClientGuard $guard;
    private readonly IssueServiceAccount $issue;
    private readonly ClaimTickets $tickets;
    private readonly DeactivateServiceAccount $deactivate;

    public function __construct(OfficialClientGuard $guard, IssueServiceAccount $issue, ClaimTickets $tickets, DeactivateServiceAccount $deactivate) {
        $this->guard = $guard;
        $this->issue = $issue;
        $this->tickets = $tickets;
        $this->deactivate = $deactivate;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse {
        $client = $this->guard->check($request);
        if ($client instanceof JsonResponse) return $client;

        $request->validate([
            'service_user_id' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'email_verified' => ['boolean'],
            'display_name' => ['nullable', 'string', 'max:100'],
            // 平文は受け取らない。移行元が保存している bcrypt ハッシュをそのまま渡してもらう
            'password_hash' => ['nullable', 'string', 'max:255'],
        ]);

        $email = $request->string('email')->trim()->toString();
        $displayName = $request->string('display_name')->trim()->toString();
        $passwordHash = $request->string('password_hash')->toString();

        $sub = $this->issue->execute(
            $client,
            $request->string('service_user_id')->toString(),
            $email === '' ? null : $email,
            $request->boolean('email_verified'),
            $displayName === '' ? null : $displayName,
            $passwordHash === '' ? null : $passwordHash,
        );

        return response()->json(['sub' => $sub]);
    }

    /**
     * 引き取り用の一度きりの URL を発行する。
     *
     * サービス側でログイン中の利用者にだけ渡してもらう前提の URL なので、
     * 誰の分かを言えるのはそのサービス自身だけ。ここでは本人確認をしない。
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function claimTicket(Request $request): JsonResponse {
        $client = $this->guard->check($request);
        if ($client instanceof JsonResponse) return $client;

        $request->validate(['service_user_id' => ['required', 'string', 'max:190']]);

        $ticket = $this->tickets->issue($client, $request->string('service_user_id')->toString());

        if ($ticket === null) {
            return ApiError::make('unknown_service_user', 'この利用者の ChreeID はまだ発行されていません', 404);
        }

        if ($ticket->link->isClaimed()) {
            return ApiError::make('already_claimed', 'このアカウントは既に引き取られています', 409);
        }

        return response()->json([
            'claim_url' => url("/claim/{$ticket->token}"),
            'expires_at' => $ticket->expiresAt->toIso8601String(),
        ]);
    }

    /**
     * 移行元でアカウントが消えたときに呼んでもらう。
     *
     * 物理削除はせず、ログインできない状態にするだけ。呼び出し元がリトライしても
     * 副作用が増えないよう、既に停止済みでも成功として扱う。
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function deactivate(Request $request): JsonResponse {
        $client = $this->guard->check($request);
        if ($client instanceof JsonResponse) return $client;

        $request->validate(['service_user_id' => ['required', 'string', 'max:190']]);

        $found = $this->deactivate->execute($client, $request->string('service_user_id')->toString());

        if (!$found) {
            return ApiError::make('unknown_service_user', 'この利用者の ChreeID は見つかりませんでした', 404);
        }

        return response()->json(['ok' => true]);
    }

}
