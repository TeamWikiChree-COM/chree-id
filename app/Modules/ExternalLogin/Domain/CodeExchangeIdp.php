<?php
namespace App\Modules\ExternalLogin\Domain;

/**
 * 認可コードで戻ってくる外部 IdP (OAuth / OIDC)。
 *
 * /auth/{provider}/callback で受けられるのはこれだけ。
 */
interface CodeExchangeIdp extends ExternalIdp {
    /**
     * 認可コードを外部アカウントの情報に交換する。
     *
     * @param string $code IdP が返した認可コード
     * @param string $nonce 発行時の nonce
     * @return ExternalIdentity
     */
    public function exchange(string $code, string $nonce): ExternalIdentity;
}
