<?php
namespace Plugins\WikiHub;

use App\Modules\Plugin\Application\PluginApi;
use App\Modules\Plugin\Domain\PluginMenu;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * DokuFarm、WikiChree などのウィキを、利用者ごとに1画面へまとめる。
 */
class WikiHubServiceProvider extends ServiceProvider {
    #[Override]
    public function register(): void {
        // 設定は利用時に読み込む。起動時に固めると、差し替えた設定が効かない
        $this->app->bind(ListWikis::class, fn (): ListWikis => new ListWikis(
            $this->app->make(PluginApi::class),
            new WikiSourceClient((int) config('wiki-hub.timeout')),
            $this->sources(),
            (int) config('wiki-hub.cache_seconds'),
        ));
    }

    /**
     * @param PluginMenu $menu
     */
    public function boot(PluginMenu $menu): void {
        $sources = $this->sources();
        // 繋いだサービスが1つも無いうちは、入口を出しても空の画面にしかならない
        if ($sources === []) return;

        $menu->addPlugin('wiki-hub', PluginMenu::AREA_DASHBOARD, array_map(static fn (WikiSource $source): string => $source->clientId, $sources));
    }

    /**
     * @return list<WikiSource>
     */
    private function sources(): array {
        return WikiSource::fromConfig((array) config('wiki-hub.sources', []));
    }
}
