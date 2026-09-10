<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\AuthIdentityAliasModel;
use App\Modules\Linking\Application\ClaimTickets;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// 既に ChreeID を持っている人が、サービスアカウントをそちらへ寄せる (統合)
class MergeServiceAccountTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('verify');
    }

    /**
     * @param string $name サービス名
     * @return OAuthClientModel
     */
    private function client(string $name = 'DokuFarm'): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', 'service-secret'),
            'name' => $name,
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
        ]);
    }

    /**
     * 寄せ元のサービスアカウントと、その入場券を作る。
     *
     * @param OAuthClientModel $client 発行元のサービス
     * @return array{token: string, accountId: string, link: ServiceAccountModel}
     */
    private function serviceAccount(OAuthClientModel $client): array {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::SERVICE, 'doku@example.com', 'DokuFarm の人');

        ServiceAccountModel::create([
            'client_id' => $client->id,
            'auth_identity_id' => $account->id,
            'service_user_id' => '42',
            'service_email' => 'doku@example.com',
        ]);

        $ticket = app(ClaimTickets::class)->issue($client, '42');
        $this->assertNotNull($ticket);

        return ['token' => $ticket->token, 'accountId' => $account->id, 'link' => $ticket->link];
    }

    /**
     * 本人が既に持っているユーザーアカウントを作り、ログインさせる。
     *
     * @param string $email 連絡先
     * @return string アカウントID (ULID)
     */
    private function signIn(string $email = 'me@example.com'): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, $email, '本人');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => $email, 'password' => 'correct-horse'])->assertRedirect();

        return $account->id;
    }

    public function test_movesTheServiceAccountIntoTheSignedInAccount(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);
        $targetId = $this->signIn();

        $this->post('/claim/merge', ['token' => $service['token']])->assertRedirect('/');

        $link = ServiceAccountModel::query()->firstOrFail();
        $this->assertSame($targetId, $link->auth_identity_id);
        $this->assertNotNull($link->claimed_at);
    }

    // 消える側のIDを指したまま来る問い合わせのために、転送表を永久に残す
    public function test_leavesAnAliasBehind(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);
        $targetId = $this->signIn();

        $this->post('/claim/merge', ['token' => $service['token']]);

        $alias = AuthIdentityAliasModel::query()->findOrFail($service['accountId']);
        $this->assertSame($targetId, $alias->current_id);
    }

    // 統合しても sub は付け替えない。指す先だけ差し替える (ARCHITECTURE 8.6)
    public function test_keepsTheSubAndOnlyRepointsIt(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);

        $before = app(ResolveSubject::class)->execute($client, $service['accountId']);

        $targetId = $this->signIn();
        $this->post('/claim/merge', ['token' => $service['token']]);

        $this->assertSame($before, app(ResolveSubject::class)->execute($client, $targetId));
    }

    // 寄せ元は残すが、以後どの経路でも入れない
    public function test_suspendsTheAccountThatDisappears(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);
        $this->signIn();

        $this->post('/claim/merge', ['token' => $service['token']]);

        $this->assertTrue(app(AuthIdentityRepository::class)->findById($service['accountId'])?->isSuspended());
    }

    // 券を持っているだけでは寄せ先が本人のものだと分からない
    public function test_refusesWhenNobodyIsSignedIn(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);

        $this->post('/claim/merge', ['token' => $service['token']])
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Claim/Failed'));

        $this->assertNull(ServiceAccountModel::query()->firstOrFail()->claimed_at);
    }

    // メールが違っても、両側が本人だと分かっていれば寄せてよい
    public function test_doesNotRequireTheEmailsToMatch(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);
        $targetId = $this->signIn('quite-different@example.com');

        $this->post('/claim/merge', ['token' => $service['token']])->assertRedirect('/');

        $this->assertSame($targetId, ServiceAccountModel::query()->firstOrFail()->auth_identity_id);
    }

    // 券は一度きり。二度目は通らない
    public function test_consumesTheTicket(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);
        $this->signIn();

        $this->post('/claim/merge', ['token' => $service['token']])->assertRedirect('/');

        $this->post('/claim/merge', ['token' => $service['token']])
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Claim/Failed'));
    }

    // 同じサービスに複数持つ形にまとめられる (WikiChree は1アカウント1Wiki)。
    // sub はサービスアカウント側にあるので、それぞれ自分の sub を保ったまま寄る
    public function test_mergesEvenWhenBothAreLinkedToTheSameService(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);
        $targetId = $this->signIn();

        // 寄せ先も同じサービスを使っている状態にする
        $targetSub = app(ResolveSubject::class)->execute($client, $targetId);
        $sourceSub = app(ResolveSubject::class)->forServiceAccount($service['link']);

        $this->post('/claim/merge', ['token' => $service['token']])->assertRedirect('/');

        $subs = ServiceAccountModel::query()
            ->where('auth_identity_id', $targetId)
            ->pluck('sub')
            ->all();

        $this->assertCount(2, $subs);
        $this->assertContains($targetSub, $subs);
        $this->assertContains($sourceSub, $subs);
    }

    // 既に本人のものになっているアカウントを、サービス経由で他人の側へ寄せられては困る
    public function test_refusesAnAccountThatIsAlreadyAUserAccount(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);
        app(UserAccounts::class)->ensure($service['accountId']);

        $this->signIn();

        $this->post('/claim/merge', ['token' => $service['token']])
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Claim/Failed'));
    }

    public function test_offersTheMergeBranchOnTheClaimScreen(): void {
        $client = $this->client();
        $service = $this->serviceAccount($client);
        $targetId = $this->signIn();

        $this->get("/claim/{$service['token']}")
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Claim/Show')
                ->where('signedInAs.id', $targetId));
    }
}
