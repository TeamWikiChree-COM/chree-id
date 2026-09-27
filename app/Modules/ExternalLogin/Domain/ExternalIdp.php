<?php
namespace App\Modules\ExternalLogin\Domain;

/**
 * 外部 IdP ひとつ分の契約。
 *
 * ChreeID が RP として外部へ「行く側」。RP が「来る側」の Provider モジュールとは向きが逆。
 *
 * 戻ってきた応答の受け取り方は IdP の方式で違うので、ここには含めない。
 * 認可コードで戻るものは CodeExchangeIdp、SAML のように別の形で戻るものはプラグインが受ける。
 */
interface ExternalIdp {
    /**
     * @return string 識別子。credentials.identifier の接頭辞になる
     */
    public function name(): string;

    /**
     * 接続に必要な設定が揃っているか。
     *
     * 揃っていない IdP はログイン画面にボタンを出さない。
     * 出してしまうと、押しても何も起きないボタンになる。
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * @param string $state CSRF 対策の値
     * @param string $nonce id_token に載せる値
     * @return string 利用者を飛ばす先
     */
    public function authorizationUrl(string $state, string $nonce): string;
}
