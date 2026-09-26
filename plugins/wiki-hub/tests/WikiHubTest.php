<?php
namespace Plugins\WikiHub\Tests;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// 連携している各サービスからウィキを集めて1画面に出す
class WikiHubTest extends TestCase {
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
        config(['wiki-hub.cache_seconds' => 0]);
    }

    /**
     * @return string アカウントID (ULID)
     */
    private function login(): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        return $account->id;
    }

    /**
     * @param string $key 設定上の名前
     * @param string|null $accountId 連携させるなら持ち主のアカウントID
     */
    private function source(string $key, ?string $accountId): void {
        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'name' => $key,
            'redirect_uris' => ["https://{$key}.example.com/callback"],
            'scopes' => 'openid',
            'is_confidential' => false,
            'trust' => ServiceTrust::OFFICIAL,
        ]);

        config(["wiki-hub.sources.{$key}" => [
            'label' => $key,
            'client_id' => $client->id,
            'endpoint' => "https://{$key}.example.com/api/wikis",
            'token' => 'secret',
        ]]);

        if ($accountId === null) return;

        ServiceAccountModel::create(['client_id' => $client->id, 'auth_identity_id' => $accountId, 'sub' => "sub-{$key}"]);
    }

    #[TestDox('ログインしていなければログイン画面へ送る')]
    public function test_requiresLogin(): void {
        $this->get('/plugins/wiki-hub')->assertRedirect('/login');
    }

    #[TestDox('http(s) 以外の URL は捨て、開けない行は一覧に出さない')]
    public function test_dropsNonWebUrls(): void {
        $id = $this->login();
        $this->source('dokufarm', $id);

        Http::fake(['dokufarm.example.com/*' => Http::response(['wikis' => [
            ['name' => '危険', 'url' => 'javascript:alert(1)'],
            ['name' => '正常', 'url' => 'https://a.example.com', 'settings_url' => 'JavaScript:alert(1)'],
        ]])]);

        $this->get('/plugins/wiki-hub')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
            ->count('groups.0.wikis', 1)
            ->where('groups.0.wikis.0.name', '正常')
            ->where('groups.0.wikis.0.settingsUrl', null)));
    }

    #[TestDox('連携しているサービスからウィキを取得し、サービスの sub と共有トークンで問い合わせる')]
    public function test_listsWikisFromConnectedService(): void {
        $id = $this->login();
        $this->source('dokufarm', $id);
        Http::fake(['dokufarm.example.com/*' => Http::response(['wikis' => [
            ['name' => 'テスト', 'url' => 'https://a.example.com', 'settings_url' => null, 'views' => 12],
        ]])]);

        $this->get('/plugins/wiki-hub')->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
            ->where('groups.0.label', 'dokufarm')
            ->where('groups.0.wikis.0.name', 'テスト')
            ->where('groups.0.wikis.0.views', 12)));

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret')
            && str_contains($request->url(), 'sub=sub-dokufarm'));
    }

    // 1つが落ちても、他のサービスの分は出す
    #[TestDox('1つのサービスが失敗しても、ほかのサービスのウィキは出す')]
    public function test_oneFailingSourceDoesNotHideOthers(): void {
        $id = $this->login();
        $this->source('dokufarm', $id);
        $this->source('wikichree', $id);
        Http::fake([
            'dokufarm.example.com/*' => Http::response('', 500),
            'wikichree.example.com/*' => Http::response(['wikis' => []]),
        ]);

        $this->get('/plugins/wiki-hub')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
            ->where('groups.0.failed', true)
            ->where('groups.1.failed', false)));
    }

    #[TestDox('連携していないサービスには問い合わせず、一覧にも出さない')]
    public function test_skipsServicesNotConnected(): void {
        $this->login();
        $this->source('dokufarm', null);
        Http::fake();

        $this->get('/plugins/wiki-hub')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload->has('groups', 0)));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'dokufarm.example.com'));
    }

    // 入口が無い (HTML の 404) のを「0件」と見せると、置き忘れに気付けない
    #[TestDox('問い合わせ先が 404 の HTML を返したら、0件ではなく失敗として出す')]
    public function test_missingEndpointIsAFailure(): void {
        $id = $this->login();
        $this->source('wikichree', $id);
        Http::fake(['wikichree.example.com/*' => Http::response('<html>404</html>', 404)]);

        $this->get('/plugins/wiki-hub')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
            ->where('groups.0.failed', true)));
    }

    #[TestDox('サービス側に利用者がいなければ、失敗ではなく0件として出す')]
    public function test_unknownUserIsEmptyNotFailure(): void {
        $id = $this->login();
        $this->source('wikichree', $id);
        Http::fake(['wikichree.example.com/*' => Http::response(['error' => 'user_not_found'], 404)]);

        $this->get('/plugins/wiki-hub')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
            ->where('groups.0.failed', false)
            ->has('groups.0.wikis', 0)));
    }
}
