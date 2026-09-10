<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// 裏で発行したアカウントを本人が引き取る (claim) 流れ
class ClaimServiceAccountTest extends TestCase {
    use RefreshDatabase;

    private const SECRET = 'service-secret';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
    }

    /**
     * @param ServiceTrust $trust 信頼状態
     * @return OAuthClientModel
     */
    private function client(ServiceTrust $trust = ServiceTrust::OFFICIAL): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', self::SECRET),
            'name' => 'DokuFarm',
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => $trust,
        ]);
    }

    /**
     * サービスアカウントを1つ発行しておく。
     *
     * @param OAuthClientModel $client 呼び出すサービス
     * @param array<string, mixed> $payload 追加の入力
     * @return void
     */
    private function issueAccount(OAuthClientModel $client, array $payload = []): void {
        $this->postJson('/api/v1/service-accounts', array_merge([
            'client_id' => $client->id,
            'client_secret' => self::SECRET,
            'service_user_id' => '42',
        ], $payload))->assertOk();
    }

    /**
     * @param OAuthClientModel $client 呼び出すサービス
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function requestTicket(OAuthClientModel $client): TestResponse {
        return $this->postJson('/api/v1/service-accounts/claim-tickets', [
            'client_id' => $client->id,
            'client_secret' => self::SECRET,
            'service_user_id' => '42',
        ]);
    }

    /**
     * 入場券の URL からトークン部分だけ取り出す。
     *
     * @param OAuthClientModel $client 呼び出すサービス
     * @return string 平文トークン
     */
    private function ticketToken(OAuthClientModel $client): string {
        $url = $this->requestTicket($client)->assertOk()->json('claim_url');
        if (!is_string($url)) $this->fail('claim_url が返っていません');

        return Str::afterLast($url, '/');
    }

    // --- 入場券の発行 ---

    public function test_issuesATicketForAnExistingServiceUser(): void {
        $client = $this->client();
        $this->issueAccount($client);

        $response = $this->requestTicket($client)->assertOk();
        $url = $response->json('claim_url');

        $this->assertIsString($url);
        $this->assertStringContainsString('/claim/', $url);
        $this->assertNotNull($response->json('expires_at'));
        $this->assertNotNull(ServiceAccountModel::query()->firstOrFail()->claim_token_hash);
    }

    // 平文を持たれると、その気になれば誰の分でも引き取れてしまう
    public function test_storesOnlyTheHashOfTheTicket(): void {
        $client = $this->client();
        $this->issueAccount($client);

        $token = $this->ticketToken($client);

        $this->assertSame(
            hash('sha256', $token),
            ServiceAccountModel::query()->firstOrFail()->claim_token_hash,
        );
    }

    public function test_refusesATicketForAnUnknownServiceUser(): void {
        $this->requestTicket($this->client())->assertStatus(404);
    }

    public function test_refusesATicketToANonOfficialService(): void {
        $this->requestTicket($this->client(ServiceTrust::APPROVED))->assertStatus(403);
    }

    public function test_refusesATicketWithoutClientAuthentication(): void {
        $client = $this->client();
        $this->issueAccount($client);

        $this->postJson('/api/v1/service-accounts/claim-tickets', [
            'client_id' => $client->id,
            'client_secret' => 'wrong',
            'service_user_id' => '42',
        ])->assertStatus(401);
    }

    // 最後に渡した1枚だけを有効にする。古い URL が漏れても使えない
    public function test_invalidatesThePreviousTicket(): void {
        $client = $this->client();
        $this->issueAccount($client);

        $old = $this->ticketToken($client);
        $this->ticketToken($client);

        $this->get("/claim/{$old}")->assertInertia(fn (Assert $page) => $page->component('Claim/Failed'));
    }

    // --- 引き取りの画面 ---

    public function test_showsTheClaimScreen(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => true, 'display_name' => '太郎']);

        $token = $this->ticketToken($client);

        $this->get("/claim/{$token}")->assertInertia(fn (Assert $page) => $page
            ->component('Claim/Show')
            ->where('serviceName', 'DokuFarm')
            ->where('email', 'user@example.com')
            ->where('emailVerified', true)
            ->where('displayName', '太郎'));
    }

    public function test_rejectsAnExpiredTicket(): void {
        $client = $this->client();
        $this->issueAccount($client);
        $token = $this->ticketToken($client);

        $this->travel(16)->minutes();

        $this->get("/claim/{$token}")->assertInertia(fn (Assert $page) => $page->component('Claim/Failed'));
    }

    public function test_rejectsAnUnknownTicket(): void {
        $this->get('/claim/' . Str::random(64))
            ->assertInertia(fn (Assert $page) => $page->component('Claim/Failed'));
    }

    // --- 引き取り ---

    public function test_claimsTheAccountAndLogsIn(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => true]);
        $token = $this->ticketToken($client);

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here', 'display_name' => '太郎'])
            ->assertRedirect('/');

        $link = ServiceAccountModel::query()->firstOrFail();
        $account = app(AuthIdentityRepository::class)->findById($link->auth_identity_id);

        $this->assertNotNull($account);
        $this->assertSame(AccountOrigin::USER, $account->origin);
        $this->assertSame('太郎', $account->displayName);
        $this->assertNotNull($link->claimed_at);
        $this->assertSame($link->auth_identity_id, session('chreeid.account_id'));
    }

    // 引き取ったあとは ChreeID 単体でも入れなければ意味がない
    public function test_theChosenPasswordWorksOnLogin(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => true]);
        $token = $this->ticketToken($client);

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here']);
        $this->post('/logout');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'chosen-here'])
            ->assertRedirect('/');
    }

    // 券は一度きり。使い回せると、漏れた URL で後からパスワードを付け替えられる
    public function test_consumesTheTicket(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => true]);
        $token = $this->ticketToken($client);

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here']);

        $this->get("/claim/{$token}")->assertInertia(fn (Assert $page) => $page->component('Claim/Failed'));
    }

    // 既に本人のものになっているアカウントは引き取らせない。
    // 許すと、サービスが券を出すだけで他人のパスワードを差し替えられてしまう
    public function test_refusesToClaimAnAccountThatIsAlreadyTheOwners(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => true]);
        $token = $this->ticketToken($client);

        // 券を配ったあとに、別経路 (統合など) で本人のものになった状況
        $accounts = app(AuthIdentityRepository::class);
        $accountId = ServiceAccountModel::query()->firstOrFail()->auth_identity_id;
        $accounts->changeOrigin($accountId, AccountOrigin::USER);
        app(SetPassword::class)->execute($accountId, 'chosen-in-chreeid');

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'taken-over'])
            ->assertInertia(fn (Assert $page) => $page->component('Claim/Failed'));

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'chosen-in-chreeid'])
            ->assertRedirect('/');
    }

    public function test_refusesATicketForAnAlreadyClaimedAccount(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => true]);
        $token = $this->ticketToken($client);
        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here']);

        $this->requestTicket($client)->assertStatus(409);
    }

    public function test_requiresALongEnoughPassword(): void {
        $client = $this->client();
        $this->issueAccount($client);
        $token = $this->ticketToken($client);

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'short'])
            ->assertSessionHasErrors('password');

        $this->assertNull(ServiceAccountModel::query()->firstOrFail()->claimed_at);
    }

    // --- 連絡先 ---

    // アドレスの無いアカウントは、ここで受け取って確認まで走らせる
    public function test_acceptsAnAddressWhenTheAccountHasNone(): void {
        $client = $this->client();
        $this->issueAccount($client);
        $token = $this->ticketToken($client);

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here', 'email' => 'new@example.com'])
            ->assertRedirect('/');

        Mail::assertSentCount(1);
    }

    public function test_rejectsAnAddressThatBelongsToSomeoneElse(): void {
        app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'taken@example.com', '別人');

        $client = $this->client();
        $this->issueAccount($client);
        $token = $this->ticketToken($client);

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertNull(ServiceAccountModel::query()->firstOrFail()->claimed_at);
    }

    // 未確認のまま引き取ったアドレスは、この機会に確かめておく
    public function test_sendsVerificationForAnUnverifiedAddress(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => false]);
        $token = $this->ticketToken($client);

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here', 'email' => 'user@example.com'])
            ->assertRedirect('/');

        Mail::assertSentCount(1);
    }

    public function test_doesNotMailWhenTheAddressIsAlreadyVerified(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => true]);
        $token = $this->ticketToken($client);

        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here', 'email' => 'user@example.com']);

        Mail::assertNothingSent();
    }

    // 引き取っても、サービスから見た sub は変わってはいけない
    public function test_keepsTheAccountTheServiceAlreadyKnows(): void {
        $client = $this->client();
        $this->issueAccount($client, ['email' => 'user@example.com', 'email_verified' => true]);
        $before = ServiceAccountModel::query()->firstOrFail()->auth_identity_id;

        $token = $this->ticketToken($client);
        $this->post('/claim', ['token' => $token, 'method' => 'password', 'password' => 'chosen-here']);

        $this->assertSame($before, ServiceAccountModel::query()->firstOrFail()->auth_identity_id);
    }
}
