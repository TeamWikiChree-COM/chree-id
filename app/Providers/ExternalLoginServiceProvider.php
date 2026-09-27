<?php
namespace App\Providers;

use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * 外部ログインの初期設定と登録
 */
class ExternalLoginServiceProvider extends ServiceProvider {
    #[Override]
    public function register(): void {
        $this->app->singleton(ExternalIdpRegistry::class);
    }

    public function boot(): void {
        // 基本的に外部IdPはプラグインとしてFacades\ExternalIdpRegistry::register() で登録する
    }
}
