<?php
namespace Plugins\Google;

use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Google ログインの入口。
 */
class GoogleServiceProvider extends ServiceProvider {
    public function boot(): void {
        ExternalIdpRegistry::register($this->app->make(GoogleIdp::class));
    }
}
