<?php
namespace App\Providers;

use App\Modules\Identity\Domain\AuthIdentityRepository;
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
    }
}
