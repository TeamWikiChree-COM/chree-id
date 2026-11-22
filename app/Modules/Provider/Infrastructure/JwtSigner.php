<?php
namespace App\Modules\Provider\Infrastructure;

use RuntimeException;

/**
 * ChreeID の署名鍵で JWT (RS256) を組み立てる。
 *
 * ライブラリを入れず openssl で署名する。RS256 は
 * base64url(header) . "." . base64url(payload) を SHA-256 で署名するだけ。
 * RP は JWKS の公開鍵で検証できるので、ID Token 以外の通知にも同じ鍵を使う。
 */
class JwtSigner {
    private readonly SigningKey $key;

    public function __construct(SigningKey $key) {
        $this->key = $key;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $type ヘッダの typ。用途の違う JWT を取り違えさせないために分ける
     * @return string
     * @throws RuntimeException 署名に失敗した場合
     */
    public function sign(array $payload, string $type = 'JWT'): string {
        $header = ['alg' => 'RS256', 'typ' => $type, 'kid' => $this->key->keyId()];
        $signingInput = $this->encode($header) . '.' . $this->encode($payload);

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $this->key->private(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('JWT の署名に失敗しました');
        }
        if (!is_string($signature)) throw new RuntimeException('署名結果を取り出せませんでした');

        return $signingInput . '.' . $this->base64Url($signature);
    }

    /**
     * @return string iss に入れる値
     */
    public function issuer(): string {
        $issuer = config('chreeid.issuer');

        return is_string($issuer) ? rtrim($issuer, '/') : '';
    }

    /**
     * @param array<string, mixed> $data
     * @return string
     * @throws RuntimeException
     */
    private function encode(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) throw new RuntimeException('JWT の組み立てに失敗しました');

        return $this->base64Url($json);
    }

    /**
     * @param string $binary
     * @return string
     */
    private function base64Url(string $binary): string {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
