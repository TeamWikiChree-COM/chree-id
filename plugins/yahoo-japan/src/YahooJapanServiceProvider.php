<?php
namespace Plugins\YahooJapan;

use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Yahoo! JAPAN ログインの入口。
 */
class YahooJapanServiceProvider extends ServiceProvider {
    public function boot(): void {
        ExternalIdpRegistry::register($this->app->make(YahooJapanIdp::class));
    }
}
