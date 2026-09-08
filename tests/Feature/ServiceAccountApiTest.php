<?php
namespace Tests\Feature;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountLinkModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

// サービスアカウントの遅延登録。利用者に登録させず、サービス利用を機に裏で発行する
class ServiceAccountApiTest extends TestCase {
    use RefreshDatabase;

    private const SECRET = 'service-secret';

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

    // 同じ人が既に ChreeID を持っているなら、そこへ寄せる
    public function test_reusesAnExistingAccountWhenBothSidesVerifiedTheAddress(): void {
        $accounts = app(ChreeAccountRepository::class);
        $existing = $accounts->create(AccountOrigin::USER, 'user@example.com', '既存');
        $accounts->markEmailVerified($existing->id);

        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true])->assertOk();

        $this->assertSame($existing->id, ServiceAccountLinkModel::query()->firstOrFail()->chree_account_id);
    }

    // アドレスの一致だけで寄せると、他人のアドレスを名乗るだけで奪える
    public function test_doesNotReuseWhenTheExistingAddressIsUnverified(): void {
        $existing = app(ChreeAccountRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', '既存');

        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => true])->assertOk();

        $this->assertNotSame($existing->id, ServiceAccountLinkModel::query()->firstOrFail()->chree_account_id);
    }

    public function test_doesNotReuseWhenTheServiceDidNotVerify(): void {
        $accounts = app(ChreeAccountRepository::class);
        $existing = $accounts->create(AccountOrigin::USER, 'user@example.com', '既存');
        $accounts->markEmailVerified($existing->id);

        $client = $this->client();
        $this->issue($client, ['email' => 'user@example.com', 'email_verified' => false])->assertOk();

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
        // サービスが何と言っていたかは控えとして残す
        $this->assertSame('user@example.com', $link->service_email);
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
