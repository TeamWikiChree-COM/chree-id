<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// サービスが自前のログインフォームのまま、照合だけこちらに任せる口
class ServiceAuthApiTest extends TestCase {
    use RefreshDatabase;

    private const SECRET = 'service-secret';
    private const EMAIL = 'user@example.com';
    private const PASSWORD = 'correct-horse';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('service-auth');
    }

    /**
     * @param ServiceTrust $trust 信頼状態
     * @param bool $confidential secret を持つクライアントか
     * @return OAuthClientModel
     */
    private function client(
        ServiceTrust $trust = ServiceTrust::OFFICIAL,
        bool $confidential = true,
    ): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => $confidential ? hash('sha256', self::SECRET) : null,
            'name' => 'DokuFarm',
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => $confidential,
            'trust' => $trust,
        ]);
    }

    /**
     * パスワードを持つサービスアカウントを、呼び出し元に紐付けて作る。
     *
     * @param OAuthClientModel|null $client 紐付け先。null なら紐付けない
     * @return string アカウントID (ULID)
     */
    private function account(?OAuthClientModel $client): string {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::SERVICE, self::EMAIL, 'テスト');
        app(SetPassword::class)->execute($account->id, self::PASSWORD);

        if ($client !== null) {
            ServiceAccountModel::create([
                'client_id' => $client->id,
                'auth_identity_id' => $account->id,
                'service_user_id' => '42',
                'service_email' => self::EMAIL,
            ]);
        }

        return $account->id;
    }

    /**
     * @param OAuthClientModel $client 呼び出すサービス
     * @param array<string, mixed> $payload 追加の入力
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function verify(OAuthClientModel $client, array $payload = []): \Illuminate\Testing\TestResponse {
        return $this->postJson('/api/v1/service-auth/password', array_merge([
            'client_id' => $client->id,
            'client_secret' => self::SECRET,
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ], $payload));
    }

    public function test_verifiesThePasswordOfItsOwnServiceUser(): void {
        $client = $this->client();
        $accountId = $this->account($client);

        $response = $this->verify($client)->assertOk();

        $response->assertJson([
            'status' => 'ok',
            'sub' => app(ResolveSubject::class)->execute($client, $accountId),
            'service_user_id' => '42',
        ]);
    }

    public function test_rejectsAWrongPassword(): void {
        $client = $this->client();
        $this->account($client);

        $this->verify($client, ['password' => 'wrong'])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_grant']);
    }

    // 登録済みかどうかを総当たりで調べられないよう、未知のアドレスも同じ応答にする
    public function test_hidesWhetherTheAccountExists(): void {
        $client = $this->client();
        $this->account($client);

        $unknown = $this->verify($client, ['email' => 'nobody@example.com'])->assertStatus(401);

        $this->assertSame(
            $this->verify($client, ['password' => 'wrong'])->json(),
            $unknown->json(),
        );
    }

    // これが無いと、この口が ChreeID 全体のパスワード試行機になる
    public function test_refusesAnAccountThatIsNotLinkedToTheCaller(): void {
        $client = $this->client();
        $this->account(null);

        $this->verify($client)
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_grant']);
    }

    // 他所のサービスに紐付いた利用者も引けない
    public function test_refusesAnAccountLinkedToAnotherService(): void {
        $other = $this->client();
        $this->account($other);

        $this->verify($this->client())
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_grant']);
    }

    // パスワードは正しくても認証は成立していない。2要素目はこの口では受け取れない
    public function test_reportsThatASecondFactorIsRequired(): void {
        $client = $this->client();
        $accountId = $this->account($client);

        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($accountId, $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));

        $this->verify($client)
            ->assertOk()
            ->assertJson(['status' => 'second_factor_required'])
            ->assertJsonMissing(['sub' => $accountId]);
    }

    public function test_refusesASuspendedAccount(): void {
        $client = $this->client();
        $accountId = $this->account($client);

        app(AuthIdentityRepository::class)->suspend($accountId);

        $this->verify($client)->assertStatus(401);
    }

    public function test_refusesANonOfficialService(): void {
        $client = $this->client(ServiceTrust::APPROVED);
        $this->account($client);

        $this->verify($client)
            ->assertStatus(403)
            ->assertJson(['error' => 'access_denied']);
    }

    public function test_refusesAPublicClient(): void {
        $client = $this->client(confidential: false);
        $this->account($client);

        $this->verify($client)
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_client']);
    }

    public function test_refusesAWrongSecret(): void {
        $client = $this->client();
        $this->account($client);

        $this->verify($client, ['client_secret' => 'nope'])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_client']);
    }

    // 平文が流れる口なので、総当たりに歯止めが要る
    public function test_throttlesRepeatedAttempts(): void {
        $client = $this->client();
        $this->account($client);

        for ($i = 0; $i < 5; $i++) {
            $this->verify($client, ['password' => 'wrong'])->assertStatus(401);
        }

        $this->verify($client)->assertStatus(429);
    }
}
