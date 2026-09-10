<?php
namespace Tests\Feature;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Registry\Application\DatabaseMigrations;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// 管理画面からのデータベース構造の適用
class AdminMigrationTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    /** 検証用に置いてあるマイグレーション */
    private const PROBE = '2000_01_01_000000_create_migration_probe_table';

    /**
     * 管理者としてログインした状態にする。
     *
     * @param string $email 名乗るアドレス
     * @return void
     */
    private function loginAs(string $email): void {
        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);

        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, $email, '管理者');
        $accounts->markEmailVerified($account->id);

        $this->withSession(['chreeid.account_id' => $account->id]);
    }

    /**
     * 未適用のマイグレーションが1件ある状態を作る。
     *
     * データベースの準備が済んだあとに置き場を足すので、
     * ここで足したぶんだけが未適用として残る。
     *
     * @return void
     */
    private function addPending(): void {
        app(Migrator::class)->path(base_path('tests/Fixtures/migrations'));
    }

    public function test_showsTheAppliedMigrations(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->get('/admin/migrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Migrations/Index')
                ->has('applied')
                ->where('pending', []));
    }

    public function test_listsAMigrationThatHasNotBeenApplied(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->addPending();

        $this->get('/admin/migrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pending', [self::PROBE]));
    }

    public function test_appliesThePendingMigrations(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->addPending();

        $this->post('/admin/migrations/run')->assertRedirect('/admin/migrations');

        // 記録が付くだけでなく、実際に構造が変わっていること
        $this->assertTrue(Schema::hasTable('migration_probe'));
        $this->assertSame([], app(DatabaseMigrations::class)->pending());
    }

    // 何が走ったか分からないまま押させたくない
    public function test_showsWhatItDid(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->addPending();

        $this->post('/admin/migrations/run');
        $output = session('migrationOutput');

        $this->assertIsString($output);
        $this->assertStringContainsString(self::PROBE, $output);
    }

    // 押しても何も起きないボタンで、余計な不安を与えない
    public function test_doesNothingWhenThereIsNothingToApply(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/admin/migrations/run')->assertRedirect('/admin/migrations');

        $this->assertNull(session('migrationOutput'));
    }

    public function test_countsThePendingOnesOnTheAdminTop(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->addPending();

        $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('pendingMigrations', 1));
    }

    // 管理者以外に構造を触らせない。あることも伏せる
    public function test_hidesThePageFromEveryoneElse(): void {
        $this->loginAs('someone@example.com');
        $this->addPending();

        $this->get('/admin/migrations')->assertNotFound();
        $this->post('/admin/migrations/run')->assertNotFound();

        $this->assertFalse(Schema::hasTable('migration_probe'));
    }

    public function test_sendsVisitorsToLogin(): void {
        $this->get('/admin/migrations')->assertRedirect('/login');
    }
}
