<?php
namespace App\Providers;

use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\EloquentChreeAccountRepository;
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
        $this->app->bind(ChreeAccountRepository::class, EloquentChreeAccountRepository::class);
    }
}
