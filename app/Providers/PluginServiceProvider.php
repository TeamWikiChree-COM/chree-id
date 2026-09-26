<?php
namespace App\Providers;

use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;
use Override;

/**
 * plugins/ に置いたプラグインを読み込む。
 *
 * プラグインは本体に組み込まずに機能を足すためのもの。本体からは
 * ServiceProvider を呼ぶだけで、ルート・マイグレーション・メニューはプラグイン自身が登録する。
 *
 * **Plugins\ の読み込みは composer に頼らない。** composer.json の autoload を変えると
 * 本番でも dump-autoload が要るが、本番は差分のアップロードしかしていない。
 */
class PluginServiceProvider extends ServiceProvider {
    #[Override]
    public function register(): void {
        $plugins = new PluginRegistry(base_path('plugins'));
        $this->app->instance(PluginRegistry::class, $plugins);
        $this->app->singleton(PluginMenu::class);

        $this->registerAutoloader($plugins->root());
        $this->registerPages($plugins);

        foreach ($plugins->all() as $plugin) {
            if ($plugin->enabled) $this->app->register($plugin->provider);
        }
    }

    /**
     * プラグインの画面 (`<プラグイン名>::<画面名>`) の置き場を Inertia に教える。
     *
     * 教えないと、画面が実在するかの確認 (テストの読み直しなど) で見つからずに落ちる。
     * finder は解決のたびに作り直されるので、登録ではなく extend で足す。
     *
     * @param PluginRegistry $plugins
     */
    private function registerPages(PluginRegistry $plugins): void {
        $this->app->extend('inertia.view-finder', static function (FileViewFinder $finder) use ($plugins): FileViewFinder {
            foreach ($plugins->all() as $plugin) {
                if ($plugin->enabled) $finder->addNamespace($plugin->name, $plugins->root() . "/{$plugin->name}/resources/js/Pages");
            }

            return $finder;
        });
    }

    /**
     * Plugins\<Studly>\Foo を plugins/<kebab>/src/Foo.php から読む。
     *
     * @param string $root plugins/ のパス
     */
    private function registerAutoloader(string $root): void {
        spl_autoload_register(static function (string $class) use ($root): void {
            if (!str_starts_with($class, 'Plugins\\')) return;

            $parts = explode('\\', substr($class, strlen('Plugins\\')));
            $plugin = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', (string) array_shift($parts)));
            $file = "{$root}/{$plugin}/src/" . implode('/', $parts) . '.php';

            if (is_file($file)) require $file;
        });
    }
}
