<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\AdoptPasswordHash;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountLinkModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// サービスアカウントの遅延登録。利用者に登録させず、サービス利用を機に裏で発行する
class ServiceAccountApiTest extends TestCase {
    use RefreshDatabase;

    private const SECRET = 'service-secret';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
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
     * @param OAuthClientModel $client 呼び出すサービス
     * @param array<string, mixed> $payload 追加の入力
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function issue(OAuthClientModel $client, array $payload = []): \Illuminate\Testing\TestResponse {
        return $this->postJson('/api/v1/service-accounts', array_merge([
            'client_id' => $client->id,
            'client_secret' => self::SECRET,
            'service_user_id' => '42',
        ], $payload));
    }

    public function test_issuesAnAccountForAServiceUser(): void {
        $client = $this->client();

        $response = $this->issue($client)->assertOk();

        $sub = $response->json('sub');
        $this->assertIsString($sub);

        $link = ServiceAccountLinkModel::query()->firstOrFail();
        $this->assertSame('42', $link->service_user_id);

        $account = app(ChreeAccountRepository::class)->findById($link->chree_account_id);
        $this->assertSame(AccountOrigin::SERVICE, $account?->origin);
    }

    // 何度叩いても増えない。サービス側が再送しても同じものが返る
    public function test_isIdempotent(): void {
        $client = $this->client();

        $first = $this->issue($client)->json('sub');
        $second = $this->issue($client)->json('sub');

        $this->assertSame($first, $second);
        $this->assertSame(1, ServiceAccountLinkModel::query()->count());
    }

    // ここが崩れると、後で OIDC ログインしたとき向こうから別人に見える
    public function test_subMatchesTheOidcSubject(): void {
        $client = $this->client();
        $sub = $this->issue($client)->json('sub');

        $link = ServiceAccountLinkModel::query()->firstOrFail();
        $viaOidc = app(ResolveSubject::class)->execute($client, $link->chree_account_id);

        $this->assertSame($sub, $viaOidc);
    }

    public function test_separatesUsersWithinTheSameService(): void {
        $client = $this->client();

        $a = $this->issue($client, ['service_user_id' => '1'])->json('sub');
        $b = $this->issue($client, ['service_user_id' => '2'])->json('sub');

        $this->assertNotSame($a, $b);
        $this->assertSame(2, ServiceAccountLinkModel::query()->count());
    }

    // アドレスが一致しても勝手に寄せない。統合するかどうかは本人が決める
    public function test_doesNotFoldIntoAnExistingAccountWithTheSameAddress(): void {
        $accounts = app(ChreeAccountRepository::class);
        $existing = $accounts->create(AccountOrigin::USER, 'user@example.com', '既存');
        $accounts->markEmailVerified($existing->id);

        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true])->assertOk();

        $this->assertNotSame($existing->id, ServiceAccountLinkModel::query()->firstOrFail()->chree_account_id);
    }

    // 使えないアドレスを新しいアカウントに持たせると、本来の持ち主が登録できなくなる
    public function test_leavesTheAddressOffWhenItBelongsToSomeoneElse(): void {
        app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'user@example.com', '既存');

        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true]);

        $link = ServiceAccountLinkModel::query()->firstOrFail();
        $account = app(ChreeAccountRepository::class)->findById($link->chree_account_id);

        $this->assertNull($account?->email);
        // 統合候補として見せるために、サービスが何と言っていたかは控えておく
        $this->assertSame('user@example.com', $link->service_email);
    }

    // 同じサービスの別利用者どうしも、アドレスが同じというだけで一緒にしない。
    // WikiChree は1アカウント1Wiki なので、同じ人が同じアドレスで複数持つ
    public function test_keepsTwoServiceUsersApartEvenWithTheSameAddress(): void {
        $client = $this->client();

        $a = $this->issue($client, ['service_user_id' => '1', 'email' => 'same@example.com', 'email_verified' => true]);
        $b = $this->issue($client, ['service_user_id' => '2', 'email' => 'same@example.com', 'email_verified' => true]);

        $this->assertNotSame($a->json('sub'), $b->json('sub'));
        $this->assertSame(2, ServiceAccountLinkModel::query()->count());
    }

    public function test_trustsAVerifiedAddressFromAnOfficialService(): void {
        $client = $this->client();
        $this->issue($client, ['email' => 'new@example.com', 'email_verified' => true]);

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);
        $this->assertTrue($account->isEmailVerified());
    }

    public function test_keepsAnUnverifiedAddressUnverified(): void {
        $client = $this->client();
        $this->issue($client, ['email' => 'new@example.com', 'email_verified' => false]);

        $this->assertFalse(app(ChreeAccountRepository::class)->findByEmail('new@example.com')?->isEmailVerified());
    }

    // --- パスワードの引き継ぎ ---

    // 移行元と同じ bcrypt なので、ハッシュをそのまま移せば平文を運ばずに済む
    public function test_adoptsTheMigratedPasswordHash(): void {
        $client = $this->client();
        $hash = password_hash('correct-horse', PASSWORD_DEFAULT);

        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true, 'password_hash' => $hash]);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/');
        $this->assertNotNull(session('chreeid.account_id'));
    }

    // 引き取り前でも移行元のパスワードで入れる。引き取りはまだ済んでいない
    public function test_theMigratedAccountIsNotClaimedYet(): void {
        $client = $this->client();
        $this->issue($client, ['password_hash' => password_hash('correct-horse', PASSWORD_DEFAULT)]);

        $this->assertNull(ServiceAccountLinkModel::query()->firstOrFail()->claimed_at);
    }

    public function test_rejectsAHashThatIsNotBcrypt(): void {
        $client = $this->client();

        $this->issue($client, ['password_hash' => 'not-a-hash'])->assertOk();

        $link = ServiceAccountLinkModel::query()->firstOrFail();
        $this->assertSame(0, CredentialModel::query()
            ->where('chree_account_id', $link->chree_account_id)
            ->where('type', CredentialType::PASSWORD)
            ->count());
    }

    // 本人が ChreeID で決め直した後に、サービスの古いハッシュで巻き戻さない
    public function test_doesNotOverwriteAPasswordTheOwnerAlreadySet(): void {
        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true]);

        $accountId = ServiceAccountLinkModel::query()->firstOrFail()->chree_account_id;
        app(SetPassword::class)->execute($accountId, 'chosen-in-chreeid');

        // 移行元がもう一度ハッシュを送ってきても、決め直した方が残る
        app(AdoptPasswordHash::class)->execute($accountId, password_hash('old-service-password', PASSWORD_DEFAULT));

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'old-service-password'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'chosen-in-chreeid'])
            ->assertRedirect('/');
    }

    // --- 呼べる相手を絞る ---

    public function test_rejectsAWrongSecret(): void {
        $client = $this->client();

        $this->postJson('/api/v1/service-accounts', [
            'client_id' => $client->id,
            'client_secret' => 'wrong',
            'service_user_id' => '42',
        ])->assertStatus(401);

        $this->assertSame(0, ServiceAccountLinkModel::query()->count());
    }

    // 承認済みの第三者に開けると、そのサービスの都合で利用者が水増しされる
    public function test_rejectsANonOfficialService(): void {
        $client = $this->client(ServiceTrust::APPROVED);

        $this->issue($client)->assertStatus(403);

        $this->assertSame(0, ServiceAccountLinkModel::query()->count());
    }

    // secret を持てないクライアントは、誰でも名乗れてしまう
    public function test_rejectsAPublicClient(): void {
        $client = $this->client(ServiceTrust::OFFICIAL, confidential: false);

        $this->postJson('/api/v1/service-accounts', [
            'client_id' => $client->id,
            'service_user_id' => '42',
        ])->assertStatus(401);
    }

    public function test_rejectsADisabledService(): void {
        $client = $this->client(ServiceTrust::DISABLED);

        $this->issue($client)->assertStatus(401);
    }

    public function test_requiresTheServiceUserId(): void {
        $client = $this->client();

        $this->issue($client, ['service_user_id' => ''])->assertStatus(422);
    }

    public function test_acceptsBasicAuthentication(): void {
        $client = $this->client();

        $this->postJson('/api/v1/service-accounts', ['service_user_id' => '42'], [
            'Authorization' => 'Basic ' . base64_encode("{$client->id}:" . self::SECRET),
        ])->assertOk();
    }
}
