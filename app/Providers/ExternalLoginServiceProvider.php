<?php
namespace App\Providers;

use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\ExternalLogin\Infrastructure\GitHubIdp;
use App\Modules\ExternalLogin\Infrastructure\GoogleIdp;
use Illuminate\Support\ServiceProvider;

/**
 * ExternalLogin モジュールの配線 (ChreeID が RP 側)
 */
class ExternalLoginServiceProvider extends ServiceProvider {
    /**
     * @return void
     */
    #[\Override]
    public function register(): void {
        $this->app->singleton(ExternalIdpRegistry::class, function (): ExternalIdpRegistry {
            $registry = new ExternalIdpRegistry();

            // 外部 IdP の一覧。増やすときはここに1行
            $registry->register($this->app->make(GoogleIdp::class));
            $registry->register($this->app->make(GitHubIdp::class));

            return $registry;
        });
    }
}
