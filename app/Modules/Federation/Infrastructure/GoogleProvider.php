<?php
namespace App\Modules\Federation\Infrastructure;

use App\Modules\Federation\Domain\FederatedIdentity;
use App\Modules\Federation\Domain\FederationProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google との連携 (ChreeID が RP 側)
 *
 * Google は OIDC プロバイダなので、id_token を読めばユーザー情報が取れる。
 */
class GoogleProvider implements FederationProvider {
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** id_token の iss はこのどちらか */
    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * @return string
     */
    public function name(): string {
        return 'google';
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
     * @return FederatedIdentity
     * @throws RuntimeException 交換や検証に失敗した場合
     */
    public function exchange(string $code, string $nonce): FederatedIdentity {
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
     * id_token の中身を読む。
     *
     * 署名の検証は省く。トークンエンドポイントから TLS で直接受け取っており、
     * クライアント認証も済んでいるため (OIDC Core 3.1.3.7)。
     * ただし aud / iss / exp / nonce は必ず確かめる。
     *
     * @param string $idToken
     * @param string $nonce 発行時の nonce
     * @return FederatedIdentity
     * @throws RuntimeException 検証に失敗した場合
     */
    private function readIdToken(string $idToken, string $nonce): FederatedIdentity {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) throw new RuntimeException('id_token の形式が不正です');

        $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($decoded === false) throw new RuntimeException('id_token を読めません');

        $claims = json_decode($decoded, true);
        if (!is_array($claims)) throw new RuntimeException('id_token を読めません');

        $this->assertClaims($claims, $nonce);

        $subject = $claims['sub'] ?? null;
        if (!is_string($subject) || $subject === '') throw new RuntimeException('sub がありません');

        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? null;

        return new FederatedIdentity(
            $this->name(),
            $subject,
            is_string($email) ? $email : null,
            ($claims['email_verified'] ?? false) === true,
            is_string($name) ? $name : null,
        );
    }

    /**
     * @param array<mixed> $claims
     * @param string $nonce
     * @return void
     * @throws RuntimeException
     */
    private function assertClaims(array $claims, string $nonce): void {
        if (!in_array($claims['iss'] ?? null, self::ISSUERS, true)) {
            throw new RuntimeException('id_token の発行者が Google ではありません');
        }
        if (($claims['aud'] ?? null) !== $this->clientId()) {
            throw new RuntimeException('id_token の宛先が一致しません');
        }
        if (($claims['nonce'] ?? null) !== $nonce) {
            throw new RuntimeException('nonce が一致しません');
        }

        $exp = $claims['exp'] ?? null;
        if (!is_int($exp) || $exp < time()) throw new RuntimeException('id_token の期限が切れています');
    }

    /** @return string */
    private function clientId(): string {
        $value = config('services.google.client_id');

        return is_string($value) ? $value : '';
    }

    /** @return string */
    private function clientSecret(): string {
        $value = config('services.google.client_secret');

        return is_string($value) ? $value : '';
    }

    /** @return string */
    private function redirectUri(): string {
        $issuer = config('chreeid.issuer');

        return (is_string($issuer) ? rtrim($issuer, '/') : '') . '/federation/google/callback';
    }
}
