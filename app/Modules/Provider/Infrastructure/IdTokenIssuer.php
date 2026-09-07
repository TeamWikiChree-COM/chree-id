<?php
namespace App\Modules\Provider\Infrastructure;

use RuntimeException;

/**
 * ID Token (JWT / RS256) の発行
 *
 * ライブラリを入れず openssl で署名する。RS256 は
 * base64url(header) . "." . base64url(payload) を SHA-256 で署名するだけ。
 */
class IdTokenIssuer {
    public function __construct(private readonly SigningKey $key) {}

    /**
     * @param string $clientId aud に入れる client_id
     * @param string $subject sub に入れる識別子 (サービスごとに異なる)
     * @param string|null $nonce 認可リクエストで受け取った値
     * @param array<string, mixed> $claims scope に応じた追加クレーム
     * @return string
     * @throws RuntimeException 署名に失敗した場合
     */
    public function issue(string $clientId, string $subject, ?string $nonce, array $claims = []): string {
        $issuer = config('chreeid.issuer');
        $ttl = config('chreeid.id_token_ttl');
        $now = time();

        $payload = array_merge($claims, [
            'iss' => is_string($issuer) ? rtrim($issuer, '/') : '',
            'sub' => $subject,
            'aud' => $clientId,
            'iat' => $now,
            'exp' => $now + (is_int($ttl) ? $ttl : 3600),
        ]);

        if ($nonce !== null) $payload['nonce'] = $nonce;

        return $this->sign([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->key->keyId(),
        ], $payload);
    }

    /**
     * @param array<string, string> $header
     * @param array<string, mixed> $payload
     * @return string
     * @throws RuntimeException
     */
    private function sign(array $header, array $payload): string {
        $signingInput = $this->encode($header) . '.' . $this->encode($payload);

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $this->key->private(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('ID Token の署名に失敗しました');
        }
        if (!is_string($signature)) throw new RuntimeException('署名結果を取り出せませんでした');

        return $signingInput . '.' . $this->base64Url($signature);
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
