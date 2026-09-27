<?php
namespace Plugins\Github;

use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * GitHub ログインの入口。
 *
 * 認可コードで戻る IdP なので、戻り先は本体の /auth/github/callback がそのまま受ける。ルートは持たない。
 */
class GithubServiceProvider extends ServiceProvider {
    public function boot(): void {
        ExternalIdpRegistry::register($this->app->make(GitHubIdp::class));
    }
}
