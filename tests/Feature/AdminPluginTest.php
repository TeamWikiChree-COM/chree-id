<?php
namespace Tests\Feature;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// 管理画面からのプラグインの有効と無効
class AdminPluginTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    private string $root;

    #[Override]
    protected function setUp(): void {
        parent::setUp();

        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);

        // 本物の plugins/ を書き換えないよう、写しを相手にする
        $this->root = sys_get_temp_dir() . '/chreeid-plugins-' . bin2hex(random_bytes(6));
        File::copyDirectory(base_path('tests/Fixtures/plugins'), $this->root);
        $this->app->instance(PluginRegistry::class, new PluginRegistry($this->root));
    }

    #[Override]
    protected function tearDown(): void {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * @param string $email
     */
    private function loginAs(string $email): void {
        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, $email, 'テスト');
        $accounts->markEmailVerified($account->id);
        $this->withSession(['chreeid.account_id' => $account->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array {
        return json_decode((string) file_get_contents($this->root . '/example/plugin.json'), true);
    }

    #[TestDox('入っているプラグインを、有効かどうかと一緒に並べる')]
    public function test_listsPlugins(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->get('/admin/plugins')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Plugins/Index')
            ->where('plugins.0.name', 'example')
            ->where('plugins.0.enabled', true));
    }

    #[TestDox('plugin.json の enabled だけを書き換え、ほかの項目は残す')]
    public function test_togglesEnabledOnly(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $before = $this->manifest();

        $this->post('/admin/plugins/example', ['enabled' => false])->assertRedirect('/admin/plugins');

        $after = $this->manifest();
        $this->assertFalse($after['enabled']);
        $this->assertSame(array_keys($before), array_keys($after));
        $this->assertSame($before['title'], $after['title']);
        $this->assertFalse((new PluginRegistry($this->root))->find('example')?->enabled);

        $this->post('/admin/plugins/example', ['enabled' => true]);
        $this->assertTrue($this->manifest()['enabled']);
    }

    #[TestDox('知らないプラグインは切り替えない')]
    public function test_rejectsUnknownPlugin(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/admin/plugins/nothing', ['enabled' => true])->assertNotFound();
    }

    #[TestDox('運営でなければ切り替えられない')]
    public function test_requiresAdmin(): void {
        $this->loginAs('user@example.com');

        $this->post('/admin/plugins/example', ['enabled' => false])->assertNotFound();
        $this->assertTrue($this->manifest()['enabled']);
    }
}
