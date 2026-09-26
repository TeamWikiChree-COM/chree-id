<?php
namespace App\Providers;

use App\Modules\Plugin\Domain\PluginManifest;
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
    /** @var array<string, true> 自動読み込みを登録済みの置き場。同じ置き場を二重に登録しない */
    private static array $autoloadedRoots = [];

    #[Override]
    public function register(): void {
        $plugins = new PluginRegistry(base_path('plugins'));
        $this->app->instance(PluginRegistry::class, $plugins);
        $this->app->singleton(PluginMenu::class);

        foreach ($plugins->enabled() as $plugin) $this->load($plugins, $plugin);
    }

    /**
     * 有効なプラグインのルートを読む。ほかのプロバイダがそろってから読むので boot に置く。
     */
    public function boot(): void {
        $plugins = $this->app->make(PluginRegistry::class);
        foreach ($plugins->enabled() as $plugin) $this->loadRoutes($plugins, $plugin);
    }

    /**
     * プラグインを1つ読み込む。設定、画面の置き場、ServiceProvider の登録まで。
     *
     * テストで plugins/ の外にあるプラグインや、無効にしてあるプラグインを読むときにも使う。
     *
     * @param PluginRegistry $plugins そのプラグインを見つけた置き場
     * @param PluginManifest $plugin
     */
    public function load(PluginRegistry $plugins, PluginManifest $plugin): void {
        $this->registerAutoloader($plugins->root());
        $this->registerPages($plugins, $plugin);
        $this->app->make(PluginMenu::class)->registerPlugin($plugin);

        $config = $plugins->path($plugin, 'config.php');
        // プラグインの register() から設定を読めるよう、プロバイダより先に登録する
        if (is_file($config)) $this->mergeConfigFrom($config, $plugin->name);

        $this->app->register($plugin->provider);
    }

    /**
     * routes/web.php を、web ミドルウェアと /plugins/<名前> の接頭辞を付けて読む。
     *
     * 接頭辞をそろえるのは、本体の URL とぶつけないため。プラグイン側は相対の URL だけ書けばよい。
     *
     * @param PluginRegistry $plugins そのプラグインを見つけた置き場
     * @param PluginManifest $plugin
     */
    public function loadRoutes(PluginRegistry $plugins, PluginManifest $plugin): void {
        if ($this->app->routesAreCached()) return;

        $routes = $plugins->path($plugin, 'routes/web.php');
        if (is_file($routes)) Route::middleware('web')->prefix("plugins/{$plugin->name}")->group($routes);
    }

    /**
     * プラグインの画面 (`<プラグイン名>::<画面名>`) の置き場を Inertia に教える。
     *
     * 教えないと、画面が実在するかの確認 (テストの読み直しなど) で見つからずに落ちる。
     * finder は解決のたびに作り直されるので、登録ではなく extend で足す。
     *
     * @param PluginRegistry $plugins
     * @param PluginManifest $plugin
     */
    private function registerPages(PluginRegistry $plugins, PluginManifest $plugin): void {
        $pages = $plugins->path($plugin, 'resources/js/Pages');

        $this->app->extend('inertia.view-finder', static function (FileViewFinder $finder) use ($plugin, $pages): FileViewFinder {
            $finder->addNamespace($plugin->name, $pages);

            return $finder;
        });
    }

    /**
     * Plugins\<Studly>\Foo を <置き場>/<kebab>/src/Foo.php から読む。
     *
     * @param string $root プラグインの置き場 (ふつうは plugins/)
     */
    private function registerAutoloader(string $root): void {
        if (isset(self::$autoloadedRoots[$root])) return;
        self::$autoloadedRoots[$root] = true;

        spl_autoload_register(static function (string $class) use ($root): void {
            if (!str_starts_with($class, 'Plugins\\')) return;

            $parts = explode('\\', substr($class, strlen('Plugins\\')));
            $plugin = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', (string) array_shift($parts)));
            $file = "{$root}/{$plugin}/src/" . implode('/', $parts) . '.php';

            if (is_file($file)) require $file;
        });
    }
}
