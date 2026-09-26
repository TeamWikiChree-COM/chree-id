<?php
namespace Tests\Feature;

use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use App\Providers\PluginServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// 本体のプラグイン読み込み。実在のプラグインの都合に左右されないよう、テスト用の example を読む
class PluginLoadingTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();

        $plugins = new PluginRegistry(base_path('tests/Fixtures/plugins'));
        $example = $plugins->find('example');
        $this->assertNotNull($example);

        $loader = $this->app->getProvider(PluginServiceProvider::class);
        $this->assertInstanceOf(PluginServiceProvider::class, $loader);

        $loader->load($plugins, $example);
        $loader->loadRoutes($plugins, $example);
    }

    #[TestDox('config.php を config(<名前>.…) で読めるようにする')]
    public function test_registersConfig(): void {
        $this->assertSame('hello', config('example.greeting'));
    }

    #[TestDox('routes/web.php を /plugins/<名前> の下に読み、画面の置き場も登録する')]
    public function test_loadsRoutesUnderThePluginPrefix(): void {
        $this->get('/plugins/example')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('example::Index')
                ->where('greeting', 'hello'));
    }

    #[TestDox('プラグインのルートには web ミドルウェアを付ける')]
    public function test_appliesWebMiddleware(): void {
        $route = Route::getRoutes()->match(Request::create('/plugins/example'));

        $this->assertContains('web', $route->middleware());
    }

    #[TestDox('プラグインの ServiceProvider が addPlugin で足した入口が、plugin.json の名前で出る')]
    public function test_addsTheEntryFromManifest(): void {
        $items = $this->app->make(PluginMenu::class)->itemsFor(PluginMenu::AREA_DASHBOARD, 'ja');

        $this->assertContains(['href' => '/plugins/example', 'label' => '例', 'description' => '読み込みのテスト用'], $items);
    }
}
