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
     * @return PublicKeyCredentialRpEntity
     */
    public function entity(): PublicKeyCredentialRpEntity {
        return PublicKeyCredentialRpEntity::create('ChreeID', $this->rpId());
    }
}
