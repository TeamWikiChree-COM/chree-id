<?php
namespace Plugins\Saml;

use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;
use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Domain\PluginMenuItem;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
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
 * Sp/ は外部の SAML IdP で ChreeID にログインする側、Idp/ は ChreeID が IdP として SAML のサービスへログインさせる側、
 * Admin/ は運営の管理画面。
 */
class SamlServiceProvider extends ServiceProvider {
    /**
     * @param PluginMenu $menu
     * @param PluginRegistry $plugins
     */
    public function boot(PluginMenu $menu, PluginRegistry $plugins): void {
        ExternalIdpRegistry::register($this->app->make(SamlIdp::class));
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // 入口は /plugins/saml ではなく管理画面なので、plugin.json の名前と説明で自分で追加する
        $manifest = $plugins->find('saml');
        if ($manifest !== null) $menu->add(new PluginMenuItem(PluginMenu::AREA_ADMIN, '/plugins/saml/admin', $manifest->title, $manifest->description));

        if ($this->app->runningInConsole()) $this->commands([GenerateIdpKeyCommand::class, AddServiceProviderCommand::class]);

        // 相手からの POST はセッションを通さずに受ける。web グループだと利用者のセッションを上書きする (README)
        if ($this->app->routesAreCached()) return;
        Route::post('/plugins/saml/acs', [SamlController::class, 'receive']);
        Route::post('/plugins/saml/idp/sso', [IdpController::class, 'receive']);
    }
}
