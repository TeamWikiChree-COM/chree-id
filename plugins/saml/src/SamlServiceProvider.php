<?php
namespace Plugins\Saml;

use App\Modules\Plugin\Application\PluginHooks;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * SAML プラグインの入口。
 */
class SamlServiceProvider extends ServiceProvider {
    /**
     * @param PluginHooks $hooks
     */
    public function boot(PluginHooks $hooks): void {
        $hooks->addExternalIdp($this->app->make(SamlIdp::class));

        // IdP からの POST はクロスサイトなので、SameSite=Lax のセッション Cookie が付かない。
        // web グループに載せると空のセッションが作られ、その Cookie で利用者のセッションを上書きしてしまう。
        // セッションも CSRF も通さずに受け、GET に回してからセッションのある側で続ける
        Route::post('/plugins/saml/acs', [SamlController::class, 'receive']);
    }
}
