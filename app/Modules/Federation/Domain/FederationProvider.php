<?php
namespace App\Modules\Federation\Domain;

/**
 * 外部 IdP ひとつ分の契約。
 *
 * ChreeID が RP として「行く側」。サービスが「来る側」の Provider モジュールとは別物。
 */
interface FederationProvider {
    /**
     * @return string 識別子。credentials.identifier の接頭辞になる
     */
    public function name(): string;

    /**
     * @param string $state CSRF 対策の値
     * @param string $nonce id_token に載せる値
     * @return string 利用者を飛ばす先
     */
    public function authorizationUrl(string $state, string $nonce): string;

    /**
     * 認可コードを外部アカウントの情報に交換する。
     *
     * @param string $code IdP が返した認可コード
     * @param string $nonce 発行時の nonce
     * @return FederatedIdentity
     */
    public function exchange(string $code, string $nonce): FederatedIdentity;
}
