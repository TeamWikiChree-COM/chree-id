<?php
namespace App\Modules\Credential\Infrastructure\Passkey;

use Webauthn\PublicKeyCredentialRpEntity;

/**
 * WebAuthn の RP (Relying Party) 情報。
 *
 * RP ID はドメインに紐づく。ここが変わると、登録済みのパスキーは一切使えなくなる。
 * DokuFarm から ChreeID へ移行するとき、パスキーだけは引き継げず再登録が要るのはこのため。
 */
class PasskeyContext {
    /**
     * @return string 例: chreeid.test
     */
    public function rpId(): string {
        $issuer = config('chreeid.issuer');
        $host = is_string($issuer) ? parse_url($issuer, PHP_URL_HOST) : null;

        return is_string($host) ? $host : 'localhost';
    }

    /**
     * @return string 例: http://chreeid.test
     */
    public function origin(): string {
        $issuer = config('chreeid.issuer');

        return is_string($issuer) ? rtrim($issuer, '/') : '';
    }

    /**
     * そのホストから見て、この RP ID が使えるか。
     *
     * WebAuthn は RP ID が origin のドメインと一致するか、その親ドメインであることを求める。
     * ずれていると `navigator.credentials.create()` がブラウザ側で必ず失敗するので、
     * options を渡す前に弾ける。
     *
     * @param string|null $host いま見られているホスト
     * @return bool
     */
    public function matchesHost(?string $host): bool {
        if ($host === null || $host === '') return false;

        $rpId = strtolower($this->rpId());
        $host = strtolower($host);

        return $host === $rpId || str_ends_with($host, ".{$rpId}");
    }

    /**
     * @return PublicKeyCredentialRpEntity
     */
    public function entity(): PublicKeyCredentialRpEntity {
        return PublicKeyCredentialRpEntity::create('ChreeID', $this->rpId());
    }
}
