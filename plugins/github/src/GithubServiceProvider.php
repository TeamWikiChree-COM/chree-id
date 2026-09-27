<?php
namespace Plugins\Github;

use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * GitHub ログインの入口。
 */
class GithubServiceProvider extends ServiceProvider {
    public function boot(): void {
        ExternalIdpRegistry::register($this->app->make(GitHubIdp::class));
    }
}
