<?php
namespace Tests\Feature;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// 管理画面のアプリケーションログ
class AdminLogTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    /**
     * 管理者としてログインした状態にする。
     *
     * @return void
     */
    private function loginAsAdmin(): void {
        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);

        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, self::ADMIN_EMAIL, '管理者');
        $accounts->markEmailVerified($account->id);

        $this->withSession(['chreeid.account_id' => $account->id]);
    }

    public function test_deliversEntriesAfterTheFirstRender(): void {
        $this->loginAsAdmin();

        // 最初の描画ではスケルトンを出すため、重い entries は後から届く
        $this->get('/admin/logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs/Index')
                ->has('files')
                ->missing('entries')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('entries')));
    }
}
