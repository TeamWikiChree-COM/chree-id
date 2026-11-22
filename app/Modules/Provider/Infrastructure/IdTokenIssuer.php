<?php
namespace App\Modules\Provider\Infrastructure;

use RuntimeException;

/**
 * ID Token (JWT / RS256) の発行
 */
class IdTokenIssuer {
    public function __construct(private readonly JwtSigner $signer) {}

    /**
     * @param string $clientId aud に入れる client_id
     * @param string $subject sub に入れる識別子 (サービスごとに異なる)
     * @param string|null $nonce 認可リクエストで受け取った値
     * @param array<string, mixed> $claims scope に応じた追加クレーム
     * @return string
     * @throws RuntimeException 署名に失敗した場合
     */
    public function issue(string $clientId, string $subject, ?string $nonce, array $claims = []): string {
        $ttl = config('chreeid.id_token_ttl');
        $now = time();

        $payload = array_merge($claims, [
            'iss' => $this->signer->issuer(),
            'sub' => $subject,
            'aud' => $clientId,
            'iat' => $now,
            'exp' => $now + (is_int($ttl) ? $ttl : 3600),
        ]);

        if ($nonce !== null) $payload['nonce'] = $nonce;

        return $this->signer->sign($payload);
    }
}
