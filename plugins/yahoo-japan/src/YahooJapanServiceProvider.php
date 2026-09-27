<?php
namespace Plugins\YahooJapan;

use App\Modules\Plugin\Application\PluginHooks;
use Illuminate\Support\ServiceProvider;

/**
 * Yahoo! JAPAN ログインの入口。
 *
 * 認可コードで戻る IdP なので、戻り先は本体の /auth/yahoo-japan/callback がそのまま受ける。ルートは持たない。
 */
class YahooJapanServiceProvider extends ServiceProvider {
    /**
     * @param PluginHooks $hooks
     */
    public function boot(PluginHooks $hooks): void {
        $hooks->addExternalIdp($this->app->make(YahooJapanIdp::class));
    }
}
