<?php
namespace App\Modules\Provider\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Provider\Infrastructure\AuthCodeModel;

/**
 * 認可コードをトークンに交換する (authorization_code グラント)。
 */
class ExchangeAuthCode {
    private readonly IssueTokens $issue;

    public function __construct(IssueTokens $issue) {
        $this->issue = $issue;
    }

    /**
     * @param OAuthClientModel $client 認証済みのクライアント
     * @param string $code 送られてきた認可コード
     * @param string $redirectUri 送られてきた redirect_uri
     * @param string $verifier 送られてきた code_verifier (PKCE)
     * @return IssuedTokens
     * @throws TokenException 交換できない場合
     */
    public function execute(OAuthClientModel $client, string $code, string $redirectUri, string $verifier): IssuedTokens {
        $row = AuthCodeModel::query()->find(hash('sha256', $code));
        if ($row === null || $row->client_id !== $client->id) throw TokenException::invalidGrant('auth_code_invalid');

        // 一度使ったコードが再び来たら、そのコードから出たトークンをまとめて失効させる (RFC 6749 4.1.2)
        if ($row->used_at !== null) {
            $this->revokeIssuedFrom($row);

            throw TokenException::invalidGrant('auth_code_reused');
        }

        if (!$row->isUsable()) throw TokenException::invalidGrant('auth_code_expired');
        if ($row->redirect_uri !== $redirectUri) throw TokenException::invalidGrant('redirect_uri_mismatch');

        $this->verifyPkce($row, $verifier);

        $row->forceFill(['used_at' => now()])->save();

        return $this->issue->execute($client, $row);
    }

    /**
     * @param AuthCodeModel $row 認可コード
     * @param string $verifier 送られてきた code_verifier
     * @return void
     * @throws TokenException 検証できない場合
     */
    private function verifyPkce(AuthCodeModel $row, string $verifier): void {
        if ($row->code_challenge === null) return;
        if ($verifier === '') throw TokenException::invalidGrant('code_verifier_required');

        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if (!hash_equals($row->code_challenge, $expected)) throw TokenException::invalidGrant('code_verifier_mismatch');
    }

    /**
     * @param AuthCodeModel $row 再利用された認可コード
     * @return void
     */
    private function revokeIssuedFrom(AuthCodeModel $row): void {
        AccessTokenModel::query()
            ->where('auth_code_hash', $row->code_hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
