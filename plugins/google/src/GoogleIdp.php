<?php
namespace Plugins\Google;

use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Infrastructure\IdTokenClaims;
use App\Modules\ExternalLogin\Domain\CodeExchangeIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdpDisplay;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google との連携 (ChreeID が RP 側)
 *
 * Google は OIDC プロバイダなので、id_token を読めばユーザー情報が取れる。
 */
class GoogleIdp implements CodeExchangeIdp {
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** id_token の iss はこのどちらか */
    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    private readonly IdTokenClaims $idTokens;

    public function __construct(IdTokenClaims $idTokens) {
        $this->idTokens = $idTokens;
    }

    /**
     * @return string
     */
    public function name(): string {
        return 'google';
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /**
     * @return ExternalIdpDisplay
     */
    public function display(): ExternalIdpDisplay {
        return ExternalIdpDisplay::brand('Google', 'google');
    }

    /**
     * @param string $state CSRF 対策の値
     * @param string $nonce id_token に載る値
     * @return string
     */
    public function authorizationUrl(string $state, string $nonce): string {
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'access_type' => 'online',
        ]);
    }

    /**
     * @param string $code Google が返した認可コード
     * @param string $nonce 発行時の nonce
     * @return ExternalIdentity
     * @throws RuntimeException 交換や検証に失敗した場合
     */
    public function exchange(string $code, string $nonce): ExternalIdentity {
        // SSL 検証は切らない。切ると中間者攻撃を許す経路が残る
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Google とのトークン交換に失敗しました');
        }

        $idToken = $response->json('id_token');
        if (!is_string($idToken)) throw new RuntimeException('id_token が返りませんでした');

        return $this->readIdToken($idToken, $nonce);
    }

    /**
     * id_token の中身を読む。検証は IdTokenClaims。
     *
     * @param string $idToken
     * @param string $nonce 発行時の nonce
     * @return ExternalIdentity
     * @throws RuntimeException 検証に失敗した場合
     */
    private function readIdToken(string $idToken, string $nonce): ExternalIdentity {
        $claims = $this->idTokens->read($idToken, self::ISSUERS, $this->clientId(), $nonce);

        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? null;

        return new ExternalIdentity(
            $this->name(),
            (string) $claims['sub'],
            is_string($email) ? $email : null,
            ($claims['email_verified'] ?? false) === true,
            is_string($name) ? $name : null,
        );
    }

    /** @return string */
    private function clientId(): string {
        $value = config('google.client_id');

        return is_string($value) ? $value : '';
    }

    /** @return string */
    private function clientSecret(): string {
        $value = config('google.client_secret');

        return is_string($value) ? $value : '';
    }

    /** @return string */
    private function redirectUri(): string {
        $issuer = config('chreeid.issuer');

        return (is_string($issuer) ? rtrim($issuer, '/') : '') . '/auth/google/callback';
    }
}
