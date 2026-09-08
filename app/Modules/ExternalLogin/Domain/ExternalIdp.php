<?php
namespace App\Modules\ExternalLogin\Domain;

/**
 * 外部 IdP ひとつ分の契約。
 *
 * ChreeID が RP として外部へ「行く側」。RP が「来る側」の Provider モジュールとは向きが逆。
 */
interface ExternalIdp {
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
     * @return ExternalIdentity
     */
    public function exchange(string $code, string $nonce): ExternalIdentity;
}
