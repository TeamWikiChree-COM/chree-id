<?php
namespace App\Providers;

use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * ExternalLogin モジュールの配線 (ChreeID が RP 側)
 */
class ExternalLoginServiceProvider extends ServiceProvider {
    #[\Override]
    public function register(): void {
        $this->app->singleton(ExternalIdpRegistry::class, function (): ExternalIdpRegistry {
            // 外部 IdP はすべてプラグインが PluginHooks::addExternalIdp() で足す (plugins/google など)
            return new ExternalIdpRegistry();
        });
    }
}
