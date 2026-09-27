<?php
namespace Plugins\YahooJapan;

use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Yahoo! JAPAN ログインの入口。
 *
 * 認可コードで戻る IdP なので、戻り先は本体の /auth/yahoo-japan/callback がそのまま受ける。ルートは持たない。
 */
class YahooJapanServiceProvider extends ServiceProvider {
    public function boot(): void {
        ExternalIdpRegistry::register($this->app->make(YahooJapanIdp::class));
    }
}
