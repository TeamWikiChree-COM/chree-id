<?php
namespace Plugins\WikiHub;

use App\Modules\Plugin\Application\PluginContext;
use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Domain\PluginMenuItem;
use Illuminate\Support\ServiceProvider;
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
    #[\Override]
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config.php', 'wiki-hub');

        $this->app->singleton(ListWikis::class, fn (): ListWikis => new ListWikis(
            $this->app->make(PluginContext::class),
            new WikiSourceClient((int) config('wiki-hub.timeout')),
            $this->sources(),
            (int) config('wiki-hub.cache_seconds'),
        ));
    }

    /**
     * @param PluginMenu $menu
     * @return void
     */
    public function boot(PluginMenu $menu): void {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $sources = $this->sources();
        // 繋いだサービスが1つも無いうちは、入口を出しても空の画面にしかならない
        if ($sources === []) return;

        $menu->add(new PluginMenuItem(
            PluginMenu::AREA_DASHBOARD,
            '/plugins/wiki-hub',
            $this->phrases('menu.label'),
            $this->phrases('menu.description'),
            array_map(static fn (WikiSource $source): string => $source->clientId, $sources),
        ));
    }

    /**
     * 本体の辞書に混ぜず、プラグインの lang/*.json から引く。
     *
     * @param string $key キー
     * @return array<string, string> ロケールごとの文言
     */
    private function phrases(string $key): array {
        $result = [];

        foreach (['ja' => 'ja_jp', 'en' => 'en_us'] as $locale => $file) {
            $catalog = json_decode((string) file_get_contents(__DIR__ . "/../lang/{$file}.json"), true);
            if (is_array($catalog) && is_string($catalog[$key] ?? null)) $result[$locale] = $catalog[$key];
        }

        return $result;
    }

    /**
     * @return list<WikiSource>
     */
    private function sources(): array {
        return WikiSource::fromConfig((array) config('wiki-hub.sources', []));
    }
}
