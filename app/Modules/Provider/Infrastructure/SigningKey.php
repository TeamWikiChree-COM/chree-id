<?php
namespace App\Modules\Provider\Infrastructure;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * ID Token の署名鍵 (RS256)
 *
 * .env の CHREEID_SIGNING_KEY に base64 化した PEM を入れる。
 * 生成は chreeid:generate-key コマンド。
 */
class SigningKey {
    private ?OpenSSLAsymmetricKey $key = null;

    /**
     * @return OpenSSLAsymmetricKey
     * @throws RuntimeException 鍵が未設定または壊れている場合
     */
    public function private(): OpenSSLAsymmetricKey {
        if ($this->key !== null) return $this->key;

        $encoded = config('chreeid.signing_key');
        if (!is_string($encoded) || $encoded === '') {
            throw new RuntimeException('CHREEID_SIGNING_KEY が未設定です。php artisan chreeid:generate-key で生成してください');
        }

        $pem = base64_decode($encoded, true);
        if ($pem === false) throw new RuntimeException('CHREEID_SIGNING_KEY を base64 として読めません');

        $key = openssl_pkey_get_private($pem);
        if ($key === false) throw new RuntimeException('CHREEID_SIGNING_KEY を秘密鍵として読めません');

        return $this->key = $key;
    }

    /**
     * 鍵の識別子。RP は kid を見て検証に使う公開鍵を選ぶ。
     *
     * 公開鍵から導出しているので、鍵を変えれば kid も変わる。
     *
     * @return string
     */
    public function keyId(): string {
        $details = $this->details();

        return substr(hash('sha256', $details['n']), 0, 16);
    }

    /**
     * JWKS に載せる公開鍵1件分
     *
     * @return array<string, string>
     */
    public function toJwk(): array {
        $details = $this->details();

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->keyId(),
            'n' => $this->base64Url($details['n']),
            'e' => $this->base64Url($details['e']),
        ];
    }

    /**
     * @return array{n: string, e: string}
     * @throws RuntimeException 公開鍵の情報を取り出せない場合
     */
    private function details(): array {
        $details = openssl_pkey_get_details($this->private());
        $rsa = is_array($details) && is_array($details['rsa'] ?? null) ? $details['rsa'] : [];
        $modulus = $rsa['n'] ?? null;
        $exponent = $rsa['e'] ?? null;

        if (!is_string($modulus) || !is_string($exponent)) {
            throw new RuntimeException('署名鍵から公開鍵の情報を取り出せません');
        }

        return ['n' => $modulus, 'e' => $exponent];
    }

    /**
     * JWK は base64url (パディング無し) で数値を持つ
     *
     * @param string $binary
     * @return string
     */
    private function base64Url(string $binary): string {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
