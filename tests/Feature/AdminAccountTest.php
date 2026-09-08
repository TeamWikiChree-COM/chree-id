<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
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
        $accounts = app(ChreeAccountRepository::class);
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

        $accounts = app(ChreeAccountRepository::class);
        $accounts->create(AccountOrigin::SERVICE, 'service@example.com', 'Bot');

        $response = $this->get('/admin/accounts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Index')
                ->has('accounts', 2)
            );

        $returned = collect($response->original->getData()['page']['props']['accounts']);

        $admin = $returned->firstWhere('email', self::ADMIN_EMAIL);
        $this->assertNotNull($admin);
        $this->assertTrue($admin['isAdmin']);
        $this->assertEquals('user', $admin['origin']);

        $bot = $returned->firstWhere('email', 'service@example.com');
        $this->assertNotNull($bot);
        $this->assertEquals('Bot', $bot['displayName']);
        $this->assertEquals('service', $bot['origin']);
    }
}
