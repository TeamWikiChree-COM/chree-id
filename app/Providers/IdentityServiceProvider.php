<?php
namespace App\Providers;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Domain\PlusAddress;
use App\Modules\Identity\Infrastructure\EloquentAuthIdentityRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Identity モジュールの配線
 */
class IdentityServiceProvider extends ServiceProvider {
    /**
     * @return void
     */
    #[\Override]
    public function register(): void {
        $this->app->bind(AuthIdentityRepository::class, EloquentAuthIdentityRepository::class);
        $this->app->singleton(
            PlusAddress::class,
            static fn (): PlusAddress => new PlusAddress(array_values(array_filter(config()->array('chreeid.plus_address_domains'), 'is_string'))),
        );
    }
}
