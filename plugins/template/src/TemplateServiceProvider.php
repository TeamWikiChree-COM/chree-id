<?php
namespace Plugins\Template;

use App\Modules\Plugin\Domain\PluginMenu;
use Illuminate\Support\ServiceProvider;

/**
 * プラグインの入口。plugin.json の provider に書いたクラスを本体が登録する。
 *
 * config.php と routes/web.php は本体が読むので、ここに読み込みの処理は要らない。
 */
class TemplateServiceProvider extends ServiceProvider {
    /**
     * Laravel が起動時に探して呼ぶ。親に宣言が無いので #[Override] は付けられない。
     * 引数は型を見てコンテナから入れてくれる。
     *
     * @param PluginMenu $menu
     */
    public function boot(PluginMenu $menu): void {
        // 名前と説明は plugin.json の title と description、URL は /plugins/template になる。
        // 特定のサービスと連携している人にだけ出すなら、3つ目に client_id の一覧を渡す
        $menu->addPlugin('template', PluginMenu::AREA_DASHBOARD);
    }
}
