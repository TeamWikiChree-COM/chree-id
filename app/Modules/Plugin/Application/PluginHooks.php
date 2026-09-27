<?php
namespace App\Modules\Plugin\Application;

use App\Modules\ExternalLogin\Domain\ExternalIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;

/**
 * プラグインが本体の処理へ差し込むための入口。
 *
 * PluginApi が「本体の状態を読む・本体に頼む」窓口なのに対し、こちらは起動時に
 * 本体の一覧や設定へ自分を足すためのもの。プラグインの boot() で使う。
 * PluginApi と同じく、本体を変えても互換性を保つ。
 *
 * @api
 */
class PluginHooks {
    private readonly ExternalIdpRegistry $idps;

    public function __construct(ExternalIdpRegistry $idps) {
        $this->idps = $idps;
    }

    /**
     * 外部 IdP を足す。ログイン画面と連携の設定画面にボタンが出る。
     *
     * 送り出しは本体の /auth/{name}/redirect が受け持つ。認可コードで戻らない IdP は、
     * 戻り先をプラグインのルートに作り、PluginApi::finishExternalLogin() へ渡す。
     *
     * @param ExternalIdp $idp
     */
    public function addExternalIdp(ExternalIdp $idp): void {
        $this->idps->register($idp);
    }
}
