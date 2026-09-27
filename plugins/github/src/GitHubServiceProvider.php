<?php
namespace Plugins\Github;

use App\Modules\Plugin\Application\PluginHooks;
use Illuminate\Support\ServiceProvider;

/**
 * GitHub ログインの入口。
 *
 * 認可コードで戻る IdP なので、戻り先は本体の /auth/github/callback がそのまま受ける。ルートは持たない。
 */
class GithubServiceProvider extends ServiceProvider {
    /**
     * @param PluginHooks $hooks
     */
    public function boot(PluginHooks $hooks): void {
        $hooks->addExternalIdp($this->app->make(GitHubIdp::class));
    }
}
