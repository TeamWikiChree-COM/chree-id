<?php
namespace App\Providers;

use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use Illuminate\Support\Facades\Route;
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
        $this->app->singleton(PluginMenu::class, static fn (): PluginMenu => new PluginMenu($plugins->enabled()));

        $this->registerAutoloader($plugins->root());
        $this->registerPages($plugins);

        foreach ($plugins->enabled() as $plugin) {
            $config = $plugins->path($plugin, 'config.php');
            // プラグインの register() から設定を読めるよう、プロバイダより先に登録する
            if (is_file($config)) $this->mergeConfigFrom($config, $plugin->name);

            $this->app->register($plugin->provider);
        }
    }

    /**
     * プラグインの routes/web.php を、web ミドルウェアと /plugins/<名前> の接頭辞を付けて読む。
     *
     * 接頭辞をそろえるのは、本体の URL とぶつけないため。プラグイン側は相対の URL だけ書けばよい。
     */
    public function boot(): void {
        if ($this->app->routesAreCached()) return;

        $plugins = $this->app->make(PluginRegistry::class);
        foreach ($plugins->enabled() as $plugin) {
            $routes = $plugins->path($plugin, 'routes/web.php');
            if (is_file($routes)) Route::middleware('web')->prefix("plugins/{$plugin->name}")->group($routes);
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
            foreach ($plugins->enabled() as $plugin) $finder->addNamespace($plugin->name, $plugins->path($plugin, 'resources/js/Pages'));

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
