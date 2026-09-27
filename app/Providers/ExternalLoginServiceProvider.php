<?php
namespace App\Providers;

use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * ExternalLogin モジュールの配線 (ChreeID が RP 側)
 */
class ExternalLoginServiceProvider extends ServiceProvider {
    /**
     * 一覧は空で用意するだけ。外部 IdP はすべてプラグインが Facades\ExternalIdpRegistry::register() で足す (plugins/google など)
     */
    #[Override]
    public function register(): void {
        $this->app->singleton(ExternalIdpRegistry::class);
    }
}
