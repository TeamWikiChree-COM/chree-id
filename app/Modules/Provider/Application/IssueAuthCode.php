<?php
namespace App\Modules\Provider\Application;

use App\Modules\Provider\Domain\AuthorizeRequest;
use App\Modules\Provider\Infrastructure\AuthCodeModel;
use Illuminate\Support\Str;

/**
 * 認可コードを発行する。
 *
 * 平文は redirect_uri に一度載せるだけで、DB にはハッシュしか残さない。
 */
class IssueAuthCode {
    /** 発行から失効までの秒数。RFC 6749 は10分以内を推奨している */
    private const EXPIRES_SECONDS = 60;

    /**
     * @param AuthorizeRequest $request 検証済みの認可リクエスト
     * @param string $accountId アカウントID (ULID)
     * @return string リダイレクトに載せる平文コード
     */
    public function execute(AuthorizeRequest $request, string $accountId): string {
        $code = Str::random(64);

        AuthCodeModel::create([
            'code_hash' => hash('sha256', $code),
            'client_id' => $request->client->id,
            'auth_identity_id' => $accountId,
            'redirect_uri' => $request->redirectUri,
            'scope' => implode(' ', $request->scopes),
            'nonce' => $request->nonce,
            'code_challenge' => $request->codeChallenge,
            'code_challenge_method' => $request->codeChallengeMethod,
            'expires_at' => now()->addSeconds(self::EXPIRES_SECONDS),
        ]);

        return $code;
    }
}
