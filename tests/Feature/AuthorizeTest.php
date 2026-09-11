<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Provider\Infrastructure\AuthCodeModel;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// OIDC の認可エンドポイント
class AuthorizeTest extends TestCase {
    use RefreshDatabase;

    private const REDIRECT_URI = 'https://rp.example.com/callback';

    /**
     * @param ServiceTrust $trust サービスの信頼状態
     * @param bool $confidential false なら PKCE 必須の public クライアント
     * @return OAuthClientModel
     */
    private function makeClient(ServiceTrust $trust = ServiceTrust::OFFICIAL, bool $confidential = true): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => 'client-' . $trust->value . ($confidential ? '' : '-public'),
            'name' => 'テストサービス',
            'redirect_uris' => [self::REDIRECT_URI],
            'scopes' => 'openid profile email',
            'is_confidential' => $confidential,
            'trust' => $trust,
            // 同意の省略は信頼状態とは別の設定。公式の既定に合わせる
            'skips_consent' => $trust === ServiceTrust::OFFICIAL,
        ]);
    }

    /**
     * @return AuthIdentity
     */
    private function loginAccount(): AuthIdentity {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        return $account;
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function authorizeQuery(OAuthClientModel $client, array $overrides = []): array {
        return array_merge([
            'client_id' => $client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid profile',
            'state' => 'xyz',
        ], $overrides);
    }

    public function test_redirectsToLoginWhenNotAuthenticated(): void {
        $client = $this->makeClient();

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)))
            ->assertRedirect('/login');
    }

    public function test_issuesCodeForOfficialClientWithoutConsent(): void {
        $client = $this->makeClient();
        $this->loginAccount();

        $response = $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)));

        $response->assertRedirectContains(self::REDIRECT_URI);
        $response->assertRedirectContains('code=');
        $response->assertRedirectContains('state=xyz');
        $this->assertSame(1, AuthCodeModel::query()->count());
    }

    public function test_showsConsentForNonOfficialClient(): void {
        $client = $this->makeClient(ServiceTrust::APPROVED);
        $this->loginAccount();

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)))->assertOk();

        $this->assertSame(0, AuthCodeModel::query()->count());
    }

    /**
     * 未登録のリダイレクト先へは飛ばさない。飛ばすとオープンリダイレクタになる。
     */
    public function test_doesNotRedirectToUnregisteredUri(): void {
        $client = $this->makeClient();
        $this->loginAccount();

        $response = $this->get('/oauth/authorize?' . http_build_query(
            $this->authorizeQuery($client, ['redirect_uri' => 'https://evil.example.com/callback']),
        ));

        // Inertia はリクエストURLをページのJSONに載せるので、文字列としては現れる。
        // 見るべきは「実際に飛んでいないこと」
        $response->assertOk();
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_doesNotRedirectWhenClientIsUnknown(): void {
        $this->loginAccount();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => 'nonexistent',
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid',
        ]))->assertOk();
    }

    public function test_rejectsDisabledService(): void {
        $client = $this->makeClient(ServiceTrust::DISABLED);
        $this->loginAccount();

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)))->assertOk();

        $this->assertSame(0, AuthCodeModel::query()->count());
    }

    /**
     * redirect_uri が確認できた後のエラーは RP へ返す
     */
    public function test_returnsErrorToClientWhenScopeIsNotAllowed(): void {
        $client = $this->makeClient();
        $this->loginAccount();

        $response = $this->get('/oauth/authorize?' . http_build_query(
            $this->authorizeQuery($client, ['scope' => 'openid admin']),
        ));

        $response->assertRedirectContains(self::REDIRECT_URI);
        $response->assertRedirectContains('error=invalid_scope');
        $response->assertRedirectContains('state=xyz');
    }

    public function test_requiresOpenidScope(): void {
        $client = $this->makeClient();
        $this->loginAccount();

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client, ['scope' => 'profile'])))
            ->assertRedirectContains('error=invalid_scope');
    }

    public function test_rejectsUnsupportedResponseType(): void {
        $client = $this->makeClient();
        $this->loginAccount();

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client, ['response_type' => 'token'])))
            ->assertRedirectContains('error=unsupported_response_type');
    }

    public function test_requiresPkceForPublicClient(): void {
        $client = $this->makeClient(ServiceTrust::OFFICIAL, false);
        $this->loginAccount();

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)))
            ->assertRedirectContains('error=invalid_request');
    }

    /**
     * plain は攻撃者が challenge をそのまま送れるので受け付けない
     */
    public function test_rejectsPlainCodeChallengeMethod(): void {
        $client = $this->makeClient();
        $this->loginAccount();

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client, [
            'code_challenge' => 'abc',
            'code_challenge_method' => 'plain',
        ])))->assertRedirectContains('error=invalid_request');
    }

    public function test_storesOnlyHashedCode(): void {
        $client = $this->makeClient();
        $this->loginAccount();

        $response = $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)));

        $location = $response->headers->get('Location') ?? '';
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $code = $params['code'] ?? null;

        $this->assertIsString($code);
        $this->assertSame(0, AuthCodeModel::query()->where('code_hash', $code)->count());
        $this->assertSame(1, AuthCodeModel::query()->where('code_hash', hash('sha256', $code))->count());
    }
}
