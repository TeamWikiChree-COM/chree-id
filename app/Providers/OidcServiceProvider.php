<?php
namespace App\Providers;

use App\Modules\Provider\Domain\Claims\ScopeRegistry as Registry;
use App\Modules\Provider\Facades\ScopeRegistry;
use App\Modules\Provider\Infrastructure\Claims\EmailClaims;
use App\Modules\Provider\Infrastructure\Claims\ProfileClaims;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * OIDCの初期設定と登録 (scopeとクレームの対応付け)
 */
class OidcServiceProvider extends ServiceProvider {
    #[Override]
    public function register(): void {
        $this->app->singleton(Registry::class);
    }

    public function boot(): void {
        ScopeRegistry::register($this->app->make(ProfileClaims::class));
        ScopeRegistry::register($this->app->make(EmailClaims::class));
    }
}
