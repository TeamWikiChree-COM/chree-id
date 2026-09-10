<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 認可コードの交換と userinfo
class TokenTest extends TestCase {
    use RefreshDatabase;

    private const REDIRECT_URI = 'https://rp.example.com/callback';
    private const SECRET = 'super-secret-value';
    private const VERIFIER = 'verifier-0123456789-0123456789-0123456789';

    /**
     * @param bool $confidential
     * @return OAuthClientModel
     */
    private function makeClient(bool $confidential = true): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => 'rp-client',
            'secret_hash' => $confidential ? hash('sha256', self::SECRET) : null,
            'name' => 'テストサービス',
            'redirect_uris' => [self::REDIRECT_URI],
            'scopes' => 'openid profile email',
            'is_confidential' => $confidential,
            'trust' => ServiceTrust::OFFICIAL,
        ]);
    }

    /**
     * 認可コードを取るところまで通す。
     *
     * @param OAuthClientModel $client
     * @param bool $withPkce
     * @return string 平文の認可コード
     */
    private function obtainCode(OAuthClientModel $client, bool $withPkce = false): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト太郎');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $query = [
            'client_id' => $client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'nonce' => 'n-0S6_WzA2Mj',
        ];

        if ($withPkce) {
            $query['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', self::VERIFIER, true)), '+/', '-_'), '=');
            $query['code_challenge_method'] = 'S256';
        }

        $location = $this->get('/oauth/authorize?' . http_build_query($query))->headers->get('Location') ?? '';
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        $code = $params['code'] ?? '';
        $this->assertIsString($code);

        return $code;
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function tokenPayload(OAuthClientModel $client, string $code, array $overrides = []): array {
        return array_merge([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::REDIRECT_URI,
            'client_id' => $client->id,
            'client_secret' => self::SECRET,
        ], $overrides);
    }

    public function test_exchangesCodeForTokens(): void {
        $client = $this->makeClient();
        $code = $this->obtainCode($client);

        $response = $this->post('/oauth/token', $this->tokenPayload($client, $code));

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'id_token', 'scope']);
        $this->assertSame('Bearer', $response->json('token_type'));
    }

    public function test_idTokenCarriesExpectedClaims(): void {
        $client = $this->makeClient();
        $code = $this->obtainCode($client);

        $idToken = $this->post('/oauth/token', $this->tokenPayload($client, $code))->json('id_token');
        $this->assertIsString($idToken);

        $parts = explode('.', $idToken);
        $this->assertCount(3, $parts);

        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        $this->assertIsArray($payload);

        $this->assertSame(config('chreeid.issuer'), $payload['iss']);
        $this->assertSame($client->id, $payload['aud']);
        $this->assertSame('n-0S6_WzA2Mj', $payload['nonce']);
        $this->assertSame('テスト太郎', $payload['name']);
        $this->assertSame('user@example.com', $payload['email']);
    }

    public function test_rejectsWrongClientSecret(): void {
        $client = $this->makeClient();
        $code = $this->obtainCode($client);

        $this->post('/oauth/token', $this->tokenPayload($client, $code, ['client_secret' => 'wrong']))
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_client');
    }

    public function test_rejectsMismatchedRedirectUri(): void {
        $client = $this->makeClient();
        $code = $this->obtainCode($client);

        $this->post('/oauth/token', $this->tokenPayload($client, $code, ['redirect_uri' => 'https://rp.example.com/other']))
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    /**
     * 再利用されたコードから出たトークンはまとめて失効させる (RFC 6749 4.1.2)
     */
    public function test_revokesTokensWhenCodeIsReused(): void {
        $client = $this->makeClient();
        $code = $this->obtainCode($client);

        $first = $this->post('/oauth/token', $this->tokenPayload($client, $code));
        $accessToken = $first->json('access_token');
        $this->assertIsString($accessToken);

        $this->post('/oauth/token', $this->tokenPayload($client, $code))
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');

        $stored = AccessTokenModel::query()->where('token_hash', hash('sha256', $accessToken))->firstOrFail();
        $this->assertNotNull($stored->revoked_at);
    }

    public function test_verifiesPkce(): void {
        $client = $this->makeClient();
        $code = $this->obtainCode($client, true);

        $this->post('/oauth/token', $this->tokenPayload($client, $code, ['code_verifier' => 'wrong-verifier']))
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_acceptsCorrectPkceVerifier(): void {
        $client = $this->makeClient();
        $code = $this->obtainCode($client, true);

        $this->post('/oauth/token', $this->tokenPayload($client, $code, ['code_verifier' => self::VERIFIER]))
            ->assertOk();
    }

    public function test_returnsUserinfoWithAccessToken(): void {
        $client = $this->makeClient();
        $code = $this->obtainCode($client);

        $accessToken = $this->post('/oauth/token', $this->tokenPayload($client, $code))->json('access_token');
        $this->assertIsString($accessToken);

        $response = $this->getJson('/oauth/userinfo', ['Authorization' => 'Bearer ' . $accessToken]);

        $response->assertOk();
        $response->assertJsonPath('email', 'user@example.com');
        $response->assertJsonPath('name', 'テスト太郎');
    }

    public function test_rejectsUserinfoWithoutToken(): void {
        $this->getJson('/oauth/userinfo')->assertStatus(401);
    }

    public function test_rejectsUnsupportedGrantType(): void {
        $client = $this->makeClient();

        $this->post('/oauth/token', ['grant_type' => 'password', 'client_id' => $client->id])
            ->assertStatus(400)
            ->assertJsonPath('error', 'unsupported_grant_type');
    }
}
