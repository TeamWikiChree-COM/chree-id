<?php
namespace Plugins\Google;

use App\Modules\Plugin\Application\PluginHooks;
use Illuminate\Support\ServiceProvider;

/**
 * Google ログインの入口。
 *
 * 認可コードで戻る IdP なので、戻り先は本体の /auth/google/callback がそのまま受ける。ルートは持たない。
 */
class GoogleServiceProvider extends ServiceProvider {
    /**
     * @param PluginHooks $hooks
     */
    public function boot(PluginHooks $hooks): void {
        $hooks->addExternalIdp($this->app->make(GoogleIdp::class));
    }
}
