<?php
namespace App\Providers;

use App\Modules\Federation\Domain\FederationRegistry;
use App\Modules\Federation\Infrastructure\GoogleProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Federation モジュールの配線 (ChreeID が RP 側)
 */
class FederationServiceProvider extends ServiceProvider {
    /**
     * @return void
     */
    #[\Override]
    public function register(): void {
        $this->app->singleton(FederationRegistry::class, function (): FederationRegistry {
            $registry = new FederationRegistry();

            // 外部 IdP の一覧。増やすときはここに1行
            $registry->register($this->app->make(GoogleProvider::class));

            return $registry;
        });
    }
}
