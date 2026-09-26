<?php
namespace Plugins\WikiHub;

use App\Modules\Plugin\Application\PluginApi;
use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Domain\PluginMenuItem;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use Illuminate\Support\ServiceProvider;
use Override;
use Plugins\WikiHub\Application\ListWikis;
use Plugins\WikiHub\Domain\WikiSource;
use Plugins\WikiHub\Infrastructure\WikiSourceClient;

/**
 * DokuFarm・WikiChree などのウィキを、利用者ごとに1画面へまとめる。
 */
class WikiHubServiceProvider extends ServiceProvider {
    /**
     * @return void
     */
    #[Override]
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config.php', 'wiki-hub');

        // 設定は使うときに読む。起動時に固めると、差し替えた設定が効かない
        $this->app->bind(ListWikis::class, fn (): ListWikis => new ListWikis(
            $this->app->make(PluginApi::class),
            new WikiSourceClient((int) config('wiki-hub.timeout')),
            $this->sources(),
            (int) config('wiki-hub.cache_seconds'),
        ));
    }

    /**
     * @param PluginMenu $menu
     * @return void
     */
    public function boot(PluginMenu $menu, PluginRegistry $plugins): void {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $sources = $this->sources();
        // 繋いだサービスが1つも無いうちは、入口を出しても空の画面にしかならない
        if ($sources === []) return;

        // 名前と説明は plugin.json の1か所だけに書く
        $manifest = $plugins->find('wiki-hub');

        $menu->add(new PluginMenuItem(
            PluginMenu::AREA_DASHBOARD,
            '/plugins/wiki-hub',
            $manifest->title ?? [],
            $manifest->description ?? [],
            array_map(static fn (WikiSource $source): string => $source->clientId, $sources),
        ));
    }

    /**
     * @return list<WikiSource>
     */
    private function sources(): array {
        return WikiSource::fromConfig((array) config('wiki-hub.sources', []));
    }
}
