<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 管理画面のアカウント一覧テスト。
 */
class AdminAccountTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);
    }

    /**
     * @param string $email
     * @param bool $verified
     * @return string
     */
    private function loginAs(string $email, bool $verified = true): string {
        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, $email, 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        if ($verified) $accounts->markEmailVerified($account->id);

        $this->post('/login', ['email' => $email, 'password' => 'correct-horse']);

        return $account->id;
    }

    /**
     * 非管理者には404を返す
     */
    public function test_hidesAccountsFromNonAdmins(): void {
        $this->loginAs('user@example.com');

        $this->get('/admin/accounts')->assertNotFound();
    }

    /**
     * 管理者がアクセスするとアカウント一覧が表示される
     */
    public function test_listsAccountsForAdmin(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $accounts = app(AuthIdentityRepository::class);
        $accounts->create(AccountOrigin::SERVICE, 'service@example.com', 'Bot');

        // 新しい順に並ぶので、あとから作った service 側が先頭に来る
        $this->get('/admin/accounts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Index')
                ->has('accounts', 2)
                ->where('accounts.0.email', 'service@example.com')
                ->where('accounts.0.displayName', 'Bot')
                ->where('accounts.0.origin', 'service')
                ->where('accounts.0.isAdmin', false)
                ->where('accounts.1.email', self::ADMIN_EMAIL)
                ->where('accounts.1.origin', 'user')
                ->where('accounts.1.isAdmin', true)
            );
    }
}
