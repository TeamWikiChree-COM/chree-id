<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Provider\Infrastructure\ServiceSubjectIdModel;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

// 利用者から見た「連携しているサービス」。管理画面の接続サービスとは別物
class ConnectedServiceTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
    }

    /**
     * @return string アカウントID (ULID)
     */
    private function login(): string {
        $account = app(ChreeAccountRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        return $account->id;
    }

    /**
     * 連携済みの状態を作る。
     *
     * sub の採番が「一度でも繋がった」証跡になるので、そことトークンを置く。
     *
     * @param string $accountId アカウントID (ULID)
     * @param bool $active 有効なトークンを残すか
     * @return string client_id
     */
    private function connect(string $accountId, bool $active = true): string {
        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', 'secret'),
            'name' => 'DokuFarm',
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
        ]);

        ServiceSubjectIdModel::create([
            'client_id' => $client->id,
            'chree_account_id' => $accountId,
            'sub' => Str::random(32),
        ]);

        AccessTokenModel::create([
            'token_hash' => hash('sha256', Str::random(64)),
            'client_id' => $client->id,
            'chree_account_id' => $accountId,
            'scope' => 'openid',
            'expires_at' => $active ? now()->addHour() : now()->subHour(),
        ]);

        return $client->id;
    }

    public function test_listsConnectedServices(): void {
        $accountId = $this->login();
        $this->connect($accountId);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->has('services', 1)
                ->where('services.0.name', 'DokuFarm')
                ->where('services.0.hasActiveToken', true));
    }

    public function test_showsNothingWhenNeverConnected(): void {
        $this->login();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('services', 0));
    }

    public function test_marksExpiredTokenAsInactive(): void {
        $accountId = $this->login();
        $this->connect($accountId, active: false);

        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->where('services.0.hasActiveToken', false));
    }

    public function test_revokesAccess(): void {
        $accountId = $this->login();
        $clientId = $this->connect($accountId);

        $this->post("/services/{$clientId}/revoke")->assertRedirect('/');

        $this->assertSame(0, AccessTokenModel::query()->whereNull('revoked_at')->count());
    }

    // sub を消すと、繋ぎ直したとき向こうから別人に見える
    public function test_keepsTheSubjectSoReconnectingIsTheSameUser(): void {
        $accountId = $this->login();
        $clientId = $this->connect($accountId);
        $before = ServiceSubjectIdModel::query()->firstOrFail()->sub;

        $this->post("/services/{$clientId}/revoke");

        $this->assertSame($before, ServiceSubjectIdModel::query()->firstOrFail()->sub);
    }

    // 解除しても記録は残るので、一覧からは消えない
    public function test_staysListedAfterRevoking(): void {
        $accountId = $this->login();
        $clientId = $this->connect($accountId);
        $this->post("/services/{$clientId}/revoke");

        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->has('services', 1)
                ->where('services.0.hasActiveToken', false));
    }

    public function test_doesNotTouchAnotherAccountsTokens(): void {
        $accountId = $this->login();
        $clientId = $this->connect($accountId);

        $other = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'other@example.com', '他人');
        AccessTokenModel::create([
            'token_hash' => hash('sha256', Str::random(64)),
            'client_id' => $clientId,
            'chree_account_id' => $other->id,
            'scope' => 'openid',
            'expires_at' => now()->addHour(),
        ]);

        $this->post("/services/{$clientId}/revoke");

        $this->assertSame(1, AccessTokenModel::query()
            ->where('chree_account_id', $other->id)
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_requiresLogin(): void {
        $this->post('/services/anything/revoke')->assertRedirect('/login');
    }
}
