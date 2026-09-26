<?php
namespace Plugins\WikiHub;

use App\Modules\Plugin\Application\PluginApi;
use App\Modules\Plugin\Domain\PluginMenu;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * DokuFarm、WikiChree などのウィキを、利用者ごとに1画面へまとめる
 */
class WikiHubServiceProvider extends ServiceProvider {
    #[Override]
    public function register(): void {
        // 設定は利用時に読み込む。起動時に固めると、差し替えた設定が効かない
        $this->app->bind(ListWikis::class, fn () => new ListWikis(
            $this->app->make(PluginApi::class),
            new WikiSourceClient((int) config('wiki-hub.timeout')),
            $this->sources(),
            (int) config('wiki-hub.cache_seconds'),
        ));
    }

    public function boot(PluginMenu $menu): void {
        $sources = $this->sources();
        if ($sources === []) return;

        $menu->addPlugin('wiki-hub', PluginMenu::AREA_DASHBOARD, array_map(static fn (WikiSource $source) => $source->clientId, $sources));
    }

    /**
     * @return list<WikiSource>
     */
    private function sources(): array {
        return WikiSource::fromConfig((array) config('wiki-hub.sources', []));
    }
}
