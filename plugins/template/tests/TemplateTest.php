<?php
namespace Plugins\Template\Tests;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use App\Providers\PluginServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// ひな形が本体の変更で動かなくなっていないかを確かめる。コピーしたら、このファイルは自分のテストに書き換える
class TemplateTest extends TestCase {
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void {
        parent::setUp();

        // ひな形は plugin.json で無効にしてあり、本体は読み込まない。テストでだけ読み込む
        $plugins = $this->app->make(PluginRegistry::class);
        $template = $plugins->find('template');
        $this->assertNotNull($template);

        $loader = $this->app->getProvider(PluginServiceProvider::class);
        $this->assertInstanceOf(PluginServiceProvider::class, $loader);

        $loader->load($plugins, $template);
        $loader->loadRoutes($plugins, $template);
    }

    #[TestDox('ログインしていれば、設定の値を渡して画面を出す')]
    public function test_showsThePage(): void {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        $this->withSession(['chreeid.account_id' => $account->id]);

        $this->get('/plugins/template')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('template::Index')
                ->where('greeting', 'Hello'));
    }

    #[TestDox('ログインしていなければログイン画面へ送る')]
    public function test_requiresLogin(): void {
        $this->get('/plugins/template')->assertRedirect('/login');
    }

    #[TestDox('ダッシュボードに入口を出す')]
    public function test_addsTheEntry(): void {
        $items = $this->app->make(PluginMenu::class)->itemsFor(PluginMenu::AREA_DASHBOARD, 'ja');

        $this->assertContains('/plugins/template', array_column($items, 'href'));
    }
}
