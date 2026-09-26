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

    public function test_requiresLogin(): void {
        $this->get('/plugins/wiki-hub')->assertRedirect('/login');
    }

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

    public function test_skipsServicesNotConnected(): void {
        $this->login();
        $this->source('dokufarm', null);
        Http::fake();

        $this->get('/plugins/wiki-hub')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload->has('groups', 0)));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'dokufarm.example.com'));
    }

    // 入口が無い (HTML の 404) のを「0件」と見せると、置き忘れに気付けない
    public function test_missingEndpointIsAFailure(): void {
        $id = $this->login();
        $this->source('wikichree', $id);
        Http::fake(['wikichree.example.com/*' => Http::response('<html>404</html>', 404)]);

        $this->get('/plugins/wiki-hub')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
            ->where('groups.0.failed', true)));
    }

    public function test_unknownUserIsEmptyNotFailure(): void {
        $id = $this->login();
        $this->source('wikichree', $id);
        Http::fake(['wikichree.example.com/*' => Http::response(['error' => 'user_not_found'], 404)]);

        $this->get('/plugins/wiki-hub')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
            ->where('groups.0.failed', false)
            ->has('groups.0.wikis', 0)));
    }
}
