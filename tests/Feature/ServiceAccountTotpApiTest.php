<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// サービスが自分の画面で利用者の TOTP を設定する。データは ChreeID に置く
class ServiceAccountTotpApiTest extends TestCase {
    use RefreshDatabase;

    private const SECRET = 'service-secret';
    private const RESOURCE = '/api/v1/service-accounts/42';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('service-account-code');
    }

    /**
     * @param string $name クライアント名
     * @return OAuthClientModel
     */
    private function client(string $name = 'WikiChree.COM'): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', self::SECRET),
            'name' => $name,
            'redirect_uris' => ['https://wiki.example.com/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'can_provision' => true,
        ]);
    }

    /**
     * パスワードを持つサービスアカウントを、呼び出し元に紐付けて作る。
     *
     * @param OAuthClientModel $client 紐付け先
     * @return string アカウントID (ULID)
     */
    private function account(OAuthClientModel $client): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::SERVICE, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        ServiceAccountModel::create([
            'client_id' => $client->id,
            'auth_identity_id' => $account->id,
            'service_user_id' => '42',
            'service_email' => 'user@example.com',
        ]);

        return $account->id;
    }

    /**
     * @param OAuthClientModel $client 呼び出すサービス
     * @param array<string, mixed> $payload 入力
     * @return array<string, mixed>
     */
    private function auth(OAuthClientModel $client, array $payload = []): array {
        return array_merge(['client_id' => $client->id, 'client_secret' => self::SECRET], $payload);
    }

    /**
     * @param string $secret base32 の秘密鍵
     * @return string 今のコード
     */
    private function codeFor(string $secret): string {
        return app(Totp::class)->at($secret, intdiv(time(), 30));
    }

    /**
     * 秘密鍵を発行して有効化まで済ませる。
     *
     * @param OAuthClientModel $client 呼び出すサービス
     * @return array{secret: string, recovery_codes: list<string>}
     */
    private function enable(OAuthClientModel $client): array {
        $secret = $this->postJson(self::RESOURCE . '/totp/secret', $this->auth($client))->assertOk()->json('secret');
        $this->assertIsString($secret);

        $codes = $this->putJson(self::RESOURCE . '/totp', $this->auth($client, ['secret' => $secret, 'code' => $this->codeFor($secret)]))
            ->assertOk()
            ->json('recovery_codes');
        $this->assertIsArray($codes);

        /** @var list<string> $codes */
        return ['secret' => $secret, 'recovery_codes' => $codes];
    }

    public function test_issuesASecretWithoutEnablingIt(): void {
        $client = $this->client();
        $accountId = $this->account($client);

        $response = $this->postJson(self::RESOURCE . '/totp/secret', $this->auth($client))->assertOk();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', (string) $response->json('secret'));
        // 認証アプリにはサービスの名前で並ぶ
        $this->assertStringContainsString('issuer=WikiChree.COM', (string) $response->json('otpauth_uri'));
        $this->assertFalse(app(CredentialRepository::class)->requiresSecondFactor($accountId));
    }

    public function test_enablesTotpWithAMatchingCode(): void {
        $client = $this->client();
        $accountId = $this->account($client);

        $result = $this->enable($client);

        $this->assertNotEmpty($result['recovery_codes']);
        $this->assertTrue(app(CredentialRepository::class)->requiresSecondFactor($accountId));
    }

    public function test_refusesAWrongCodeWhenEnabling(): void {
        $client = $this->client();
        $accountId = $this->account($client);
        $secret = $this->postJson(self::RESOURCE . '/totp/secret', $this->auth($client))->json('secret');

        $this->putJson(self::RESOURCE . '/totp', $this->auth($client, ['secret' => $secret, 'code' => '000000']))
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_code');

        $this->assertFalse(app(CredentialRepository::class)->requiresSecondFactor($accountId));
    }

    public function test_disablesTotpWithTheCurrentCode(): void {
        $client = $this->client();
        $accountId = $this->account($client);
        $result = $this->enable($client);

        $this->deleteJson(self::RESOURCE . '/totp', $this->auth($client, ['code' => $this->codeFor($result['secret'])]))
            ->assertNoContent();

        $credentials = app(CredentialRepository::class);
        $this->assertFalse($credentials->requiresSecondFactor($accountId));
        // 2FA が無いのに復旧コードが残っていても使い道が無い
        $this->assertFalse($credentials->has($accountId, CredentialType::RECOVERY_CODE));
    }

    // サービス側のセッションを乗っ取っただけでは外せない
    public function test_refusesToDisableWithAWrongCode(): void {
        $client = $this->client();
        $accountId = $this->account($client);
        $this->enable($client);

        $this->deleteJson(self::RESOURCE . '/totp', $this->auth($client, ['code' => '000000']))
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_code');

        $this->assertTrue(app(CredentialRepository::class)->requiresSecondFactor($accountId));
    }

    public function test_disablesTotpWithARecoveryCode(): void {
        $client = $this->client();
        $accountId = $this->account($client);
        $result = $this->enable($client);

        $this->deleteJson(self::RESOURCE . '/totp', $this->auth($client, ['code' => $result['recovery_codes'][0]]))
            ->assertNoContent();

        $this->assertFalse(app(CredentialRepository::class)->requiresSecondFactor($accountId));
    }

    public function test_regeneratesRecoveryCodes(): void {
        $client = $this->client();
        $this->account($client);
        $result = $this->enable($client);

        $codes = $this->postJson(self::RESOURCE . '/recovery-codes', $this->auth($client, ['code' => $this->codeFor($result['secret'])]))
            ->assertOk()
            ->json('recovery_codes');

        $this->assertIsArray($codes);
        $this->assertNotEquals($result['recovery_codes'], $codes);
    }

    public function test_reportsWhenTotpIsNotEnabled(): void {
        $client = $this->client();
        $this->account($client);

        $this->deleteJson(self::RESOURCE . '/totp', $this->auth($client, ['code' => '123456']))
            ->assertStatus(409)
            ->assertJsonPath('error', 'not_enabled');
    }

    // 本人のものになっていたら、本人が ChreeID で管理する。サービス経由では触らせない
    public function test_refusesAUserAccount(): void {
        $client = $this->client();
        $accountId = $this->account($client);
        app(UserAccounts::class)->ensure($accountId);

        $this->postJson(self::RESOURCE . '/totp/secret', $this->auth($client))
            ->assertStatus(409)
            ->assertJsonPath('error', 'managed');
    }

    public function test_refusesAnotherServicesUser(): void {
        $this->account($this->client());
        $other = $this->client('Other');

        $this->postJson(self::RESOURCE . '/totp/secret', $this->auth($other))->assertNotFound();
    }

    public function test_throttlesCodeAttempts(): void {
        $client = $this->client();
        $this->account($client);
        $this->enable($client);

        // 有効化で1回使っている
        for ($i = 0; $i < 4; $i++) {
            $this->deleteJson(self::RESOURCE . '/totp', $this->auth($client, ['code' => '000000']))->assertStatus(422);
        }

        $this->deleteJson(self::RESOURCE . '/totp', $this->auth($client, ['code' => '000000']))->assertStatus(429);
    }
}
