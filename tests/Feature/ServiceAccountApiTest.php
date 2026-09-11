<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\AdoptPasswordHash;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Application\ResolveByEmail;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
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
            // 発行権限は信頼状態とは別の設定。公式の既定に合わせる
            'can_provision' => $trust === ServiceTrust::OFFICIAL,
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

    /**
     * @param OAuthClientModel $client 呼び出すサービス
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function askStatus(OAuthClientModel $client): \Illuminate\Testing\TestResponse {
        return $this->postJson('/api/v1/service-accounts/status', [
            'client_id' => $client->id,
            'client_secret' => self::SECRET,
            'service_user_id' => '42',
        ]);
    }

    public function test_issuesAnAccountForAServiceUser(): void {
        $client = $this->client();

        $response = $this->issue($client)->assertOk();

        $sub = $response->json('sub');
        $this->assertIsString($sub);

        $link = ServiceAccountModel::query()->firstOrFail();
        $this->assertSame('42', $link->service_user_id);

        $account = app(AuthIdentityRepository::class)->findById($link->auth_identity_id);
        $this->assertSame(AccountOrigin::SERVICE, $account?->origin);
    }

    // 何度叩いても増えない。サービス側が再送しても同じものが返る
    public function test_isIdempotent(): void {
        $client = $this->client();

        $first = $this->issue($client)->json('sub');
        $second = $this->issue($client)->json('sub');

        $this->assertSame($first, $second);
        $this->assertSame(1, ServiceAccountModel::query()->count());
    }

    // ここが崩れると、後で OIDC ログインしたとき向こうから別人に見える
    public function test_subMatchesTheOidcSubject(): void {
        $client = $this->client();
        $sub = $this->issue($client)->json('sub');

        $link = ServiceAccountModel::query()->firstOrFail();
        $viaOidc = app(ResolveSubject::class)->execute($client, $link->auth_identity_id);

        $this->assertSame($sub, $viaOidc);
    }

    public function test_separatesUsersWithinTheSameService(): void {
        $client = $this->client();

        $a = $this->issue($client, ['service_user_id' => '1'])->json('sub');
        $b = $this->issue($client, ['service_user_id' => '2'])->json('sub');

        $this->assertNotSame($a, $b);
        $this->assertSame(2, ServiceAccountModel::query()->count());
    }

    // アドレスが一致しても勝手に寄せない。統合するかどうかは本人が決める
    public function test_doesNotFoldIntoAnExistingAccountWithTheSameAddress(): void {
        $accounts = app(AuthIdentityRepository::class);
        $existing = $accounts->create(AccountOrigin::USER, 'user@example.com', '既存');
        $accounts->markEmailVerified($existing->id);

        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true])->assertOk();

        $this->assertNotSame($existing->id, ServiceAccountModel::query()->firstOrFail()->auth_identity_id);
    }

    // 使えないアドレスを新しいアカウントに持たせると、本来の持ち主が登録できなくなる
    // 同じアドレスを既に誰かが使っていても、そのまま持たせる。
    // 同一サービスに複数アカウントを持つ人は普通そこに同じアドレスを使う (ARCHITECTURE 8.3)
    public function test_keepsTheAddressEvenWhenSomeoneElseUsesIt(): void {
        app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', '既存');

        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true]);

        $link = ServiceAccountModel::query()->firstOrFail();
        $account = app(AuthIdentityRepository::class)->findById($link->auth_identity_id);

        $this->assertNotNull($account);
        $this->assertSame('user@example.com', $account->email);
        $this->assertTrue($account->isEmailVerified());
        // 統合候補として見せるために、サービスが何と言っていたかも控えておく
        $this->assertSame('user@example.com', $link->service_email);
    }

    // Google だけで使っていた人は、これが無いと認証手段0件のアカウントになる
    public function test_carriesExternalIdentities(): void {
        $client = $this->client();
        $this->issue($client, ['external' => ['google:123456']]);

        $link = ServiceAccountModel::query()->firstOrFail();

        $this->assertTrue(\App\Modules\Credential\Infrastructure\CredentialModel::query()
            ->where('auth_identity_id', $link->auth_identity_id)
            ->where('type', \App\Modules\Credential\Domain\CredentialType::OAUTH)
            ->where('identifier', 'google:123456')
            ->exists());
    }

    // provider と subject が揃っていないものは受け取らない
    public function test_ignoresMalformedExternalIdentities(): void {
        $client = $this->client();
        $this->issue($client, ['external' => ['nonsense']]);

        $link = ServiceAccountModel::query()->firstOrFail();

        $this->assertSame(0, \App\Modules\Credential\Infrastructure\CredentialModel::query()
            ->where('auth_identity_id', $link->auth_identity_id)
            ->where('type', \App\Modules\Credential\Domain\CredentialType::OAUTH)
            ->count());
    }

    // 発行権限は信頼状態とは別。承認済みでも許可されていれば使える
    public function test_allowsAnApprovedServiceThatMayProvision(): void {
        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', self::SECRET),
            'name' => 'VPS-Search',
            'redirect_uris' => ['https://search.example.com/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::APPROVED,
            'can_provision' => true,
        ]);

        $this->issue($client)->assertOk();
    }

    // 公式でも、許可されていなければ使えない
    public function test_refusesAnOfficialServiceThatMayNotProvision(): void {
        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', self::SECRET),
            'name' => '公式だが発行しない',
            'redirect_uris' => ['https://rp.example.com/callback'],
            'scopes' => 'openid',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'can_provision' => false,
        ]);

        $this->issue($client)->assertStatus(403);
    }

    // 発行のあとに移行元で Google を繋いだ人を、遡って拾えるようにする
    public function test_topsUpExternalIdentitiesOnAnExistingLink(): void {
        $client = $this->client();
        $this->issue($client);

        $this->issue($client, ['external' => ['google:123456']])->assertOk();

        $link = ServiceAccountModel::query()->firstOrFail();
        $this->assertTrue(\App\Modules\Credential\Infrastructure\CredentialModel::query()
            ->where('auth_identity_id', $link->auth_identity_id)
            ->where('identifier', 'google:123456')
            ->exists());

        // 何度叩いても増えない
        $this->issue($client, ['external' => ['google:123456']]);
        $this->assertSame(1, \App\Modules\Credential\Infrastructure\CredentialModel::query()
            ->where('auth_identity_id', $link->auth_identity_id)
            ->where('type', \App\Modules\Credential\Domain\CredentialType::OAUTH)
            ->count());
    }

    // 移行元が「まだ渡していないもの」を判断できるように返す
    public function test_reportsWhichCredentialsTheAccountHas(): void {
        $client = $this->client();
        $this->issue($client, ['password_hash' => password_hash('x', PASSWORD_BCRYPT)]);

        $this->askStatus($client)->assertOk()->assertJson(['credential_types' => ['password']]);
    }

    // 有効化していないと RequestMagicLink が黙って何もしない。
    // 移行元がメールリンクで入らせているなら、引き継がないとその経路が消える
    public function test_carriesTheMagicLinkCapability(): void {
        $client = $this->client();
        $this->issue($client, ['magic_link' => true])->assertOk();

        $link = ServiceAccountModel::query()->firstOrFail();

        $this->assertTrue(app(\App\Modules\Credential\Domain\CredentialRepository::class)
            ->has($link->auth_identity_id, \App\Modules\Credential\Domain\CredentialType::MAGIC_LINK));
    }

    public function test_doesNotEnableMagicLinkUnlessAsked(): void {
        $client = $this->client();
        $this->issue($client)->assertOk();

        $link = ServiceAccountModel::query()->firstOrFail();

        $this->assertFalse(app(\App\Modules\Credential\Domain\CredentialRepository::class)
            ->has($link->auth_identity_id, \App\Modules\Credential\Domain\CredentialType::MAGIC_LINK));
    }

    // --- パスワードの反映 ---

    /**
     * @param OAuthClientModel $client 呼び出すサービス
     * @param string $hash 反映するハッシュ
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function sendPassword(OAuthClientModel $client, string $hash): \Illuminate\Testing\TestResponse {
        return $this->postJson('/api/v1/service-accounts/password', [
            'client_id' => $client->id,
            'client_secret' => self::SECRET,
            'service_user_id' => '42',
            'password_hash' => $hash,
        ]);
    }

    // 流し忘れると古いパスワードが通り続けるので、変更は必ず届く必要がある
    public function test_appliesAChangedPassword(): void {
        $client = $this->client();
        $this->issue($client, ['password_hash' => password_hash('old', PASSWORD_BCRYPT)]);

        $new = password_hash('brand-new', PASSWORD_BCRYPT);
        $this->sendPassword($client, $new)->assertOk();

        $link = ServiceAccountModel::query()->firstOrFail();
        $stored = \App\Modules\Credential\Infrastructure\CredentialModel::query()
            ->where('auth_identity_id', $link->auth_identity_id)
            ->where('type', \App\Modules\Credential\Domain\CredentialType::PASSWORD)
            ->firstOrFail();

        $this->assertTrue(password_verify('brand-new', (string)$stored->secret));
        $this->assertFalse(password_verify('old', (string)$stored->secret));
    }

    // 本人のものになっていたら、サービス経由では触らせない
    public function test_refusesToChangeThePasswordOfAUserAccount(): void {
        $client = $this->client();
        $this->issue($client, ['password_hash' => password_hash('old', PASSWORD_BCRYPT)]);

        $link = ServiceAccountModel::query()->firstOrFail();
        app(\App\Modules\Identity\Application\UserAccounts::class)->ensure($link->auth_identity_id);

        $this->sendPassword($client, password_hash('taken-over', PASSWORD_BCRYPT))->assertStatus(409);
    }

    // bcrypt 以外を書くと、パスワードはあるのに絶対に通らないアカウントになる
    public function test_refusesANonBcryptHash(): void {
        $client = $this->client();
        $this->issue($client);

        $this->sendPassword($client, 'not-a-bcrypt-hash')->assertStatus(409);
    }

    // --- 状態の照会 ---

    // 参照だけの口。移行の導線を出すかどうかをサービスが決めるために使う
    public function test_reportsThatTheUserHasNotMigratedYet(): void {
        $client = $this->client();
        $this->issue($client);

        $this->askStatus($client)->assertOk()->assertJson(['migrated' => false]);
    }

    public function test_reportsThatTheUserHasMigrated(): void {
        $client = $this->client();
        $this->issue($client);

        $link = ServiceAccountModel::query()->firstOrFail();
        app(\App\Modules\Identity\Application\UserAccounts::class)->ensure($link->auth_identity_id);

        $this->askStatus($client)->assertOk()->assertJson(['migrated' => true]);
    }

    // 券を発行してしまうと、画面を出すたびに前の券が無効になる
    public function test_doesNotIssueATicket(): void {
        $client = $this->client();
        $this->issue($client);

        $this->askStatus($client)->assertOk();

        $this->assertNull(ServiceAccountModel::query()->firstOrFail()->claim_token_hash);
    }

    public function test_refusesStatusForAnUnknownServiceUser(): void {
        $this->askStatus($this->client())->assertStatus(404);
    }

    public function test_refusesStatusWithoutClientAuthentication(): void {
        $client = $this->client();
        $this->issue($client);

        $this->postJson('/api/v1/service-accounts/status', [
            'client_id' => $client->id,
            'client_secret' => 'wrong',
            'service_user_id' => '42',
        ])->assertStatus(401);
    }

    // 同じサービスの別利用者どうしも、アドレスが同じというだけで一緒にしない。
    // WikiChree は1アカウント1Wiki なので、同じ人が同じアドレスで複数持つ
    public function test_keepsTwoServiceUsersApartEvenWithTheSameAddress(): void {
        $client = $this->client();

        $a = $this->issue($client, ['service_user_id' => '1', 'email' => 'same@example.com', 'email_verified' => true]);
        $b = $this->issue($client, ['service_user_id' => '2', 'email' => 'same@example.com', 'email_verified' => true]);

        $this->assertNotSame($a->json('sub'), $b->json('sub'));
        $this->assertSame(2, ServiceAccountModel::query()->count());
    }

    public function test_trustsAVerifiedAddressFromAnOfficialService(): void {
        $client = $this->client();
        $this->issue($client, ['email' => 'new@example.com', 'email_verified' => true]);

        $account = app(ResolveByEmail::class)->primary('new@example.com');
        $this->assertNotNull($account);
        $this->assertTrue($account->isEmailVerified());
    }

    public function test_keepsAnUnverifiedAddressUnverified(): void {
        $client = $this->client();
        $this->issue($client, ['email' => 'new@example.com', 'email_verified' => false]);

        $this->assertFalse(app(ResolveByEmail::class)->primary('new@example.com')?->isEmailVerified());
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

        $this->assertNull(ServiceAccountModel::query()->firstOrFail()->claimed_at);
    }

    public function test_rejectsAHashThatIsNotBcrypt(): void {
        $client = $this->client();

        $this->issue($client, ['password_hash' => 'not-a-hash'])->assertOk();

        $link = ServiceAccountModel::query()->firstOrFail();
        $this->assertSame(0, CredentialModel::query()
            ->where('auth_identity_id', $link->auth_identity_id)
            ->where('type', CredentialType::PASSWORD)
            ->count());
    }

    // 本人が ChreeID で決め直した後に、サービスの古いハッシュで巻き戻さない
    public function test_doesNotOverwriteAPasswordTheOwnerAlreadySet(): void {
        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true]);

        $accountId = ServiceAccountModel::query()->firstOrFail()->auth_identity_id;
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

        $this->assertSame(0, ServiceAccountModel::query()->count());
    }

    // 承認済みの第三者に開けると、そのサービスの都合で利用者が水増しされる
    public function test_rejectsANonOfficialService(): void {
        $client = $this->client(ServiceTrust::APPROVED);

        $this->issue($client)->assertStatus(403);

        $this->assertSame(0, ServiceAccountModel::query()->count());
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

    /**
     * @param OAuthClientModel $client 呼び出すサービス
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function retire(OAuthClientModel $client): \Illuminate\Testing\TestResponse {
        return $this->postJson('/api/v1/service-accounts/deactivate', [
            'client_id' => $client->id,
            'client_secret' => self::SECRET,
            'service_user_id' => '42',
        ]);
    }

    // 退会: サービス上の人格しか無ければ、認証主体ごと消す
    public function test_retirementRemovesAnUnclaimedIdentityOutright(): void {
        $client = $this->client();
        $this->issue($client, ['password_hash' => password_hash('x', PASSWORD_BCRYPT)]);

        $identityId = ServiceAccountModel::query()->firstOrFail()->auth_identity_id;

        $this->retire($client)->assertOk();

        $this->assertSame(0, ServiceAccountModel::query()->count());
        $this->assertNull(app(AuthIdentityRepository::class)->findById($identityId));

        // cascade で認証情報も残らない
        $this->assertFalse(CredentialModel::query()
            ->where('auth_identity_id', $identityId)->exists());
    }

    // 退会: 移行済みなら ChreeID 本体は残す。
    // 一律に停止していた頃は、他サービスからも入れなくなっていた
    public function test_retirementKeepsAnIdentityThatHasAUserAccount(): void {
        $client = $this->client();
        $this->issue($client);

        $identityId = ServiceAccountModel::query()->firstOrFail()->auth_identity_id;
        \App\Modules\Identity\Infrastructure\UserAccountModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'auth_identity_id' => $identityId,
        ]);

        $this->retire($client)->assertOk();

        $this->assertSame(0, ServiceAccountModel::query()->count());

        $identity = app(AuthIdentityRepository::class)->findById($identityId);
        $this->assertNotNull($identity);
        $this->assertNull($identity->suspendedAt);
    }

    // 他のサービスで使っている人を巻き添えにしない
    public function test_retirementKeepsAnIdentityThatStillServesAnotherService(): void {
        $client = $this->client();
        $other = $this->client();
        $this->issue($client);

        $link = ServiceAccountModel::query()->firstOrFail();
        ServiceAccountModel::create([
            'client_id' => $other->id,
            'auth_identity_id' => $link->auth_identity_id,
            'service_user_id' => '99',
        ]);

        $this->retire($client)->assertOk();

        $this->assertNotNull(app(AuthIdentityRepository::class)->findById($link->auth_identity_id));
        $this->assertSame(1, ServiceAccountModel::query()->count());
    }

    // 畳んだあとの再送は 404。呼び出し元は投げっぱなしでよい
    public function test_retirementIsGoneOnASecondCall(): void {
        $client = $this->client();
        $this->issue($client);

        $this->retire($client)->assertOk();
        $this->retire($client)->assertStatus(404);
    }
}
