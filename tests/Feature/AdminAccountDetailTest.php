<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// 管理画面の検索・詳細・不整合の修正
class AdminAccountDetailTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    protected function setUp(): void {
        parent::setUp();

        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);
        $accounts = app(AuthIdentityRepository::class);
        $admin = $accounts->create(AccountOrigin::USER, self::ADMIN_EMAIL, '管理者');
        $accounts->markEmailVerified($admin->id);
        app(UserAccounts::class)->ensure($admin->id);
        $this->withSession(['chreeid.account_id' => $admin->id]);
    }

    /**
     * @param string $email
     * @return string アカウントID
     */
    private function serviceAccount(string $email): string {
        $id = app(AuthIdentityRepository::class)->create(AccountOrigin::SERVICE, $email, null)->id;
        app(SetPassword::class)->execute($id, 'correct-horse');

        return $id;
    }

    /**
     * @param string $accountId
     * @param string $clientId
     * @param string $serviceUserId
     * @return ServiceAccountModel
     */
    private function link(string $accountId, string $clientId, string $serviceUserId): ServiceAccountModel {
        OAuthClientModel::query()->firstOrCreate(['id' => $clientId], [
            'name' => $clientId,
            'redirect_uris' => ['https://rp.example.com/cb'],
            'scopes' => 'openid',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
        ]);

        return ServiceAccountModel::create([
            'client_id' => $clientId,
            'auth_identity_id' => $accountId,
            'service_user_id' => $serviceUserId,
            'sub' => 'sub-' . $serviceUserId,
        ]);
    }

    // 問い合わせはサービス側の識別子で来ることが多い
    public function test_searchesByServiceUserId(): void {
        $this->link($this->serviceAccount('a@example.com'), 'dokufarm', '248');
        $this->serviceAccount('b@example.com');

        $this->get('/admin/accounts?q=248')->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.email', 'a@example.com')
            ->where('pagination.total', 1));
    }

    public function test_filtersByKind(): void {
        $this->serviceAccount('svc@example.com');

        $this->get('/admin/accounts?kind=service')->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.email', 'svc@example.com'));
    }

    public function test_showsTheDetail(): void {
        $id = $this->serviceAccount('svc@example.com');
        $this->link($id, 'dokufarm', '248');

        $this->get("/admin/accounts/{$id}")->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Accounts/Show')
            ->has('credentials', 1)
            ->has('links', 1)
            ->where('links.0.sub', 'sub-248'));
    }

    // 最後の1件を消すと本人が入れなくなる
    public function test_refusesToRemoveTheLastCredential(): void {
        $id = $this->serviceAccount('svc@example.com');
        $credential = CredentialModel::query()->where('auth_identity_id', $id)->firstOrFail();

        $this->post("/admin/accounts/{$id}/credentials/{$credential->id}/delete")->assertSessionHasErrors('account');

        $this->assertTrue(CredentialModel::query()->whereKey($credential->id)->exists());
    }

    // 分離しても sub は変わらない
    #[TestDox('分離しても sub は変わらない')]
    public function test_splitsAServiceAccountKeepingItsSub(): void {
        $id = $this->serviceAccount('svc@example.com');
        $this->link($id, 'dokufarm', '248');
        $wiki = $this->link($id, 'wikichree', 'uuid-1');
        $credential = CredentialModel::query()->where('auth_identity_id', $id)->firstOrFail();

        $this->post("/admin/accounts/{$id}/links/{$wiki->id}/split", ['credentials' => [$credential->id]])
            ->assertRedirect("/admin/accounts/{$id}");

        $wiki->refresh();
        $this->assertNotSame($id, $wiki->auth_identity_id);
        $this->assertSame('sub-uuid-1', $wiki->sub);
    }

    public function test_unlinksAServiceAccount(): void {
        $id = $this->serviceAccount('svc@example.com');
        $link = $this->link($id, 'dokufarm', '248');

        $this->post("/admin/accounts/{$id}/links/{$link->id}/delete")->assertRedirect("/admin/accounts/{$id}");

        $this->assertFalse(ServiceAccountModel::query()->whereKey($link->id)->exists());
    }

    // 画面から来たIDは信用しない。別のアカウントの紐付けは触らせない
    public function test_refusesToUnlinkAnotherAccountsLink(): void {
        $id = $this->serviceAccount('a@example.com');
        $other = $this->link($this->serviceAccount('b@example.com'), 'dokufarm', '248');

        $this->post("/admin/accounts/{$id}/links/{$other->id}/delete")->assertSessionHasErrors('account');

        $this->assertTrue(ServiceAccountModel::query()->whereKey($other->id)->exists());
    }

    public function test_listsAndFixesIssues(): void {
        $id = $this->serviceAccount('svc@example.com');
        $this->link($id, 'dokufarm', '248');
        $this->link($id, 'wikichree', 'uuid-1');

        $this->get('/admin/accounts/issues')->assertInertia(fn (Assert $page) => $page
            ->missing('accounts')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('accounts', 1)
                ->where('accounts.0.issues', ['multi_service'])));

        $this->post('/admin/accounts/issues/fix')->assertRedirect('/admin/accounts/issues');

        $this->assertTrue(app(UserAccounts::class)->exists($id));
        $this->get('/admin/accounts/issues')->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('accounts', 0)));
    }
}
