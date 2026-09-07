<?php
namespace App\Providers;

use App\Modules\Provider\Domain\Claims\ScopeRegistry;
use App\Modules\Provider\Infrastructure\Claims\EmailClaims;
use App\Modules\Provider\Infrastructure\Claims\ProfileClaims;
use Illuminate\Support\ServiceProvider;

/**
 * OIDC (Provider モジュール) の配線
 */
class OidcServiceProvider extends ServiceProvider {
    /**
     * @return void
     */
    #[\Override]
    public function register(): void {
        $this->app->singleton(ScopeRegistry::class, function (): ScopeRegistry {
            $registry = new ScopeRegistry();

            // scope とクレームの対応。増やすときはここに1行
            $registry->register($this->app->make(ProfileClaims::class));
            $registry->register($this->app->make(EmailClaims::class));

            return $registry;
        });
    }
}
