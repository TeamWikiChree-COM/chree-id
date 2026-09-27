<?php
namespace App\Providers;

use App\Modules\Provider\Domain\Claims\ScopeRegistry as Registry;
use App\Modules\Provider\Facades\ScopeRegistry;
use App\Modules\Provider\Infrastructure\Claims\EmailClaims;
use App\Modules\Provider\Infrastructure\Claims\ProfileClaims;
use Illuminate\Support\ServiceProvider;

/**
 * OIDC (Provider モジュール) の配線
 */
class OidcServiceProvider extends ServiceProvider {
    /**
     * 一覧は空で用意するだけ。中身は boot() で、プラグインと同じ Facade から入れる。
     */
    #[\Override]
    public function register(): void {
        $this->app->singleton(Registry::class);
    }

    /**
     * scope とクレームの対応。増やすときはここに1行
     */
    public function boot(): void {
        ScopeRegistry::register($this->app->make(ProfileClaims::class));
        ScopeRegistry::register($this->app->make(EmailClaims::class));
    }
}
