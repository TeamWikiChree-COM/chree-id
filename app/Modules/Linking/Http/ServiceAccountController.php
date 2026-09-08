<?php
namespace App\Modules\Linking\Http;

use App\Modules\Linking\Application\IssueServiceAccount;
use App\Modules\Registry\Application\AuthenticateClient;
use App\Modules\Registry\Domain\ServiceTrust;
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
    public function __construct(
        private readonly AuthenticateClient $clients,
        private readonly IssueServiceAccount $issue,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse {
        $client = $this->clients->execute($request);

        if ($client === null || !$client->is_confidential) {
            return $this->error('invalid_client', 'クライアント認証に失敗しました', 401);
        }

        if ($client->trust !== ServiceTrust::OFFICIAL) {
            return $this->error('access_denied', 'このサービスはアカウントを発行できません', 403);
        }

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
     * @param string $error 機械可読なコード
     * @param string $description 人が読む説明
     * @param int $status HTTP ステータス
     * @return JsonResponse
     */
    private function error(string $error, string $description, int $status): JsonResponse {
        return response()->json(['error' => $error, 'error_description' => $description], $status);
    }
}
