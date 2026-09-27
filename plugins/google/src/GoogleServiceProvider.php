<?php
namespace Plugins\Google;

use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Google ログインの入口。
 *
 * 認可コードで戻る IdP なので、戻り先は本体の /auth/google/callback がそのまま受ける。ルートは持たない。
 */
class GoogleServiceProvider extends ServiceProvider {
    public function boot(): void {
        ExternalIdpRegistry::register($this->app->make(GoogleIdp::class));
    }
}
