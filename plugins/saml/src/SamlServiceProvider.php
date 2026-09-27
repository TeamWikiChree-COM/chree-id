<?php
namespace Plugins\Saml;

use App\Modules\Plugin\Application\PluginHooks;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\Saml\Idp\AddServiceProviderCommand;
use Plugins\Saml\Idp\GenerateIdpKeyCommand;
use Plugins\Saml\Idp\IdpController;
use Plugins\Saml\Sp\SamlController;
use Plugins\Saml\Sp\SamlIdp;

/**
 * SAML プラグインの入口。
 *
 * Sp/ は外部の SAML IdP で ChreeID にログインする側、Idp/ は ChreeID が IdP として SAML のサービスへログインさせる側。
 */
class SamlServiceProvider extends ServiceProvider {
    /**
     * @param PluginHooks $hooks
     */
    public function boot(PluginHooks $hooks): void {
        $hooks->addExternalIdp($this->app->make(SamlIdp::class));
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) $this->commands([GenerateIdpKeyCommand::class, AddServiceProviderCommand::class]);

        // 相手からの POST はクロスサイトなので、SameSite=Lax のセッション Cookie が付かない。
        // web グループに載せると空のセッションが作られ、その Cookie で利用者のセッションを上書きしてしまう。
        // セッションも CSRF も通さずに受け、GET に回してからセッションのある側で続ける
        if ($this->app->routesAreCached()) return;
        Route::post('/plugins/saml/acs', [SamlController::class, 'receive']);
        Route::post('/plugins/saml/idp/sso', [IdpController::class, 'receive']);
    }
}
