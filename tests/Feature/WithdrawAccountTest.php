<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// 退会。すぐには消さず、猶予を過ぎてから行ごと消す
class WithdrawAccountTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
    }

    /**
     * ログイン済みのアカウントを用意する。
     *
     * @return string アカウントID (ULID)
     */
    private function login(): string {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $id = session('chreeid.account_id');
        $this->assertIsString($id);

        return $id;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return void
     */
    private function withdraw(string $accountId): void {
        $this->post('/settings/withdraw', ['understood' => true])->assertRedirect('/login');
        $this->assertTrue(app(AuthIdentityRepository::class)->findById($accountId)?->isDeleted());
    }

    public function test_marksTheAccountAsWithdrawn(): void {
        $accountId = $this->login();

        $this->withdraw($accountId);

        // 猶予のあいだ行は残る
        $this->assertNotNull(app(AuthIdentityRepository::class)->findById($accountId));
    }

    // 判定を増やすと取りこぼすので、退会は既存の停止にも乗せている
    public function test_blocksLoginRightAway(): void {
        $accountId = $this->login();
        $this->withdraw($accountId);

        $this->assertTrue(app(AuthIdentityRepository::class)->findById($accountId)?->isSuspended());

        RateLimiter::clear('login');
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertSessionHasErrors();

        $this->assertNull(session('chreeid.account_id'));
    }

    // 行が残っているあいだ、連携先のトークンが生き続けては困る
    public function test_revokesIssuedTokens(): void {
        $accountId = $this->login();

        $client = \App\Modules\Registry\Infrastructure\OAuthClientModel::create([
            'id' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ulid()->toString()),
            'secret_hash' => hash('sha256', 'x'),
            'name' => 'DokuFarm',
            'redirect_uris' => ['https://doku.example.com/cb'],
            'scopes' => 'openid',
            'is_confidential' => true,
        ]);

        AccessTokenModel::create([
            'id' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ulid()->toString()),
            'auth_identity_id' => $accountId,
            'client_id' => $client->id,
            'token_hash' => hash('sha256', 'tok'),
            'scope' => 'openid',
            'expires_at' => now()->addHour(),
        ]);

        $this->withdraw($accountId);

        $this->assertNotNull(AccessTokenModel::query()->firstOrFail()->revoked_at);
    }

    // 説明を読み飛ばして押せてしまわないようにする
    public function test_requiresTheExplicitAcknowledgement(): void {
        $accountId = $this->login();

        $this->post('/settings/withdraw', [])->assertSessionHasErrors('understood');

        $this->assertFalse(app(AuthIdentityRepository::class)->findById($accountId)?->isDeleted());
    }

    // 猶予のあいだは消えない
    public function test_purgeKeepsAccountsInsideTheGracePeriod(): void {
        $accountId = $this->login();
        $this->withdraw($accountId);

        $this->assertSame(0, app(PurgeDeletedAccounts::class)->execute());
        $this->assertNotNull(app(AuthIdentityRepository::class)->findById($accountId));
    }

    // 猶予を過ぎたら行ごと消える。認証情報も cascade で消える
    public function test_purgeRemovesAccountsPastTheGracePeriod(): void {
        $accountId = $this->login();
        $this->withdraw($accountId);

        $this->travel(PurgeDeletedAccounts::graceDays() + 1)->days();

        $this->assertSame(1, app(PurgeDeletedAccounts::class)->execute());
        $this->assertNull(app(AuthIdentityRepository::class)->findById($accountId));

        $this->assertFalse(\App\Modules\Credential\Infrastructure\CredentialModel::query()
            ->where('auth_identity_id', $accountId)->exists());
    }

    // 退会していないアカウントを巻き添えにしない
    public function test_purgeLeavesLivingAccountsAlone(): void {
        $this->login();

        $this->travel(PurgeDeletedAccounts::graceDays() + 1)->days();

        $this->assertSame(0, app(PurgeDeletedAccounts::class)->execute());
    }

    public function test_sendsVisitorsToLogin(): void {
        $this->get('/settings/withdraw')->assertRedirect('/login');
        $this->post('/settings/withdraw', ['understood' => true])->assertRedirect('/login');
    }
}
