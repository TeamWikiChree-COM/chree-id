<?php
namespace Tests\Feature;

use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// 管理画面からのアカウント操作
class AdminAccountManageTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    /**
     * 管理者としてログインした状態にする。
     *
     * @return string 管理者のアカウントID (ULID)
     */
    private function loginAsAdmin(): string {
        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);

        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, self::ADMIN_EMAIL, '管理者');
        $accounts->markEmailVerified($account->id);

        $this->withSession(['chreeid.account_id' => $account->id]);

        return $account->id;
    }

    /**
     * 操作される側のアカウントを用意する。
     *
     * @return string アカウントID (ULID)
     */
    private function target(): string {
        return app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'target@example.com', '対象')->id;
    }

    /**
     * @param string $id 対象のアカウントID
     * @param string $action 操作
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function act(string $id, string $action): \Illuminate\Testing\TestResponse {
        return $this->post("/admin/accounts/{$id}/act", ['action' => $action]);
    }

    public function test_createsAnAccountWithAUserAccount(): void {
        $this->loginAsAdmin();

        $this->post('/admin/accounts', ['email' => 'new@example.com', 'display_name' => '新規'])
            ->assertRedirect('/admin/accounts');

        $account = app(AuthIdentityRepository::class)->findAllByEmail('new@example.com')[0] ?? null;
        $this->assertNotNull($account);
        $this->assertTrue(app(UserAccounts::class)->exists($account->id));
    }

    // 管理者が決めたパスワードは本人以外が知っている状態になる
    public function test_theCreatedAccountHasNoCredentials(): void {
        $this->loginAsAdmin();

        $this->post('/admin/accounts', ['email' => 'new@example.com', 'display_name' => null]);

        $account = app(AuthIdentityRepository::class)->findAllByEmail('new@example.com')[0] ?? null;
        $this->assertNotNull($account);
        $this->assertFalse(CredentialModel::query()->where('auth_identity_id', $account->id)->exists());
    }

    public function test_editsTheDisplayNameAndEmail(): void {
        $this->loginAsAdmin();
        $id = $this->target();

        $this->post("/admin/accounts/{$id}", ['display_name' => '変更後', 'email' => 'moved@example.com'])
            ->assertRedirect('/admin/accounts');

        $account = app(AuthIdentityRepository::class)->findById($id);
        $this->assertNotNull($account);
        $this->assertSame('変更後', $account->displayName);
        $this->assertSame('moved@example.com', $account->email);
    }

    public function test_suspendsAndReleases(): void {
        $this->loginAsAdmin();
        $id = $this->target();

        $this->act($id, 'suspend')->assertRedirect('/admin/accounts');
        $suspended = app(AuthIdentityRepository::class)->findById($id);
        $this->assertNotNull($suspended);
        $this->assertTrue($suspended->isSuspended());

        $this->act($id, 'unsuspend')->assertRedirect('/admin/accounts');
        $released = app(AuthIdentityRepository::class)->findById($id);
        $this->assertNotNull($released);
        $this->assertFalse($released->isSuspended());
    }

    // 解除しても deleted_at が残り、中途半端な状態になる
    public function test_refusesToReleaseAWithdrawnAccount(): void {
        $this->loginAsAdmin();
        $id = $this->target();
        $this->act($id, 'withdraw');

        $this->act($id, 'unsuspend')->assertSessionHasErrors('account');

        $this->assertTrue(app(AuthIdentityRepository::class)->findById($id)?->isSuspended());
    }

    public function test_withdrawsAndRestores(): void {
        $this->loginAsAdmin();
        $id = $this->target();

        $this->act($id, 'withdraw')->assertRedirect('/admin/accounts');
        $account = app(AuthIdentityRepository::class)->findById($id);
        $this->assertTrue($account?->isDeleted());
        $this->assertTrue($account->isSuspended());

        $this->act($id, 'restore')->assertRedirect('/admin/accounts');
        $account = app(AuthIdentityRepository::class)->findById($id);
        $this->assertFalse($account?->isDeleted());
        $this->assertFalse($account->isSuspended());
    }

    public function test_purgesWithoutWaitingForTheGracePeriod(): void {
        $this->loginAsAdmin();
        $id = $this->target();

        $this->act($id, 'purge')->assertRedirect('/admin/accounts');

        $this->assertNull(app(AuthIdentityRepository::class)->findById($id));
    }

    // 締め出されると管理画面に戻れなくなる
    public function test_refusesToActOnYourself(): void {
        $adminId = $this->loginAsAdmin();

        foreach (['suspend', 'withdraw', 'purge'] as $action) {
            $this->act($adminId, $action)->assertSessionHasErrors('account');
        }

        $account = app(AuthIdentityRepository::class)->findById($adminId);
        $this->assertNotNull($account);
        $this->assertFalse($account->isSuspended());
        $this->assertFalse($account->isDeleted());
    }

    public function test_refusesToEditYourself(): void {
        $adminId = $this->loginAsAdmin();

        $this->post("/admin/accounts/{$adminId}", ['display_name' => '書き換え', 'email' => null])
            ->assertSessionHasErrors('account');

        $this->assertSame('管理者', app(AuthIdentityRepository::class)->findById($adminId)?->displayName);
    }

    // 退会済みも出す。隠すと猶予のあいだ戻せることに気付けない
    public function test_listsWithdrawnAccountsToo(): void {
        $this->loginAsAdmin();
        $id = $this->target();
        $this->act($id, 'withdraw');

        $this->get('/admin/accounts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Index')
                ->has('accounts', 2));
    }

    public function test_hidesEverythingFromNonAdmins(): void {
        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);
        $accounts = app(AuthIdentityRepository::class);
        $intruder = $accounts->create(AccountOrigin::USER, 'someone@example.com', '部外者');
        $accounts->markEmailVerified($intruder->id);
        $this->withSession(['chreeid.account_id' => $intruder->id]);

        $id = $this->target();

        $this->get('/admin/accounts')->assertNotFound();
        $this->post('/admin/accounts', ['email' => 'x@example.com'])->assertNotFound();
        $this->act($id, 'purge')->assertNotFound();

        $this->assertNotNull($accounts->findById($id));
    }
}
