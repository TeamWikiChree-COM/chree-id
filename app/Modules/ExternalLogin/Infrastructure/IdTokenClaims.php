<?php
namespace App\Modules\ExternalLogin\Infrastructure;

use RuntimeException;

/**
 * 外部 IdP (OIDC) の id_token を読み、中身を確かめる。
 *
 * 署名の検証は省く。トークンエンドポイントから TLS で直接受け取っており、
 * クライアント認証も済んでいるため (OIDC Core 3.1.3.7)。
 * ただし iss / aud / exp / nonce は必ず確かめる。
 */
class IdTokenClaims {
    /**
     * @param string $idToken
     * @param list<string> $issuers 受け入れる iss
     * @param string $clientId こちらの client_id。aud に入っていなければならない
     * @param string $nonce 発行時の nonce
     * @return array<string, mixed> 確かめ済みのクレーム
     * @throws RuntimeException 読めない、または確かめられない場合
     */
    public function read(string $idToken, array $issuers, string $clientId, string $nonce): array {
        $claims = $this->decode($idToken);

        if (!in_array($claims['iss'] ?? null, $issuers, true)) throw new RuntimeException('id_token の発行者が一致しません');
        if (!$this->isForUs($claims, $clientId)) throw new RuntimeException('id_token の宛先が一致しません');
        if (($claims['nonce'] ?? null) !== $nonce) throw new RuntimeException('nonce が一致しません');

        $exp = $claims['exp'] ?? null;
        if (!is_int($exp) || $exp < time()) throw new RuntimeException('id_token の期限が切れています');

        $subject = $claims['sub'] ?? null;
        if (!is_string($subject) || $subject === '') throw new RuntimeException('sub がありません');

        return $claims;
    }

    /**
     * @param string $idToken
     * @return array<string, mixed>
     */
    private function decode(string $idToken): array {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) throw new RuntimeException('id_token の形式が不正です');

        $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($claims)) throw new RuntimeException('id_token を読めません');

        return $claims;
    }

    /**
     * aud は文字列でも配列でもよい (OIDC Core 2)。配列で相手が複数なら、azp が自分であることまで見る。
     *
     * @param array<string, mixed> $claims
     * @param string $clientId
     * @return bool
     */
    private function isForUs(array $claims, string $clientId): bool {
        $aud = $claims['aud'] ?? null;
        if (is_string($aud)) return $aud === $clientId;
        if (!is_array($aud) || !in_array($clientId, $aud, true)) return false;

        return count($aud) === 1 || ($claims['azp'] ?? null) === $clientId;
    }
}
