<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// 接続サービスの管理画面。本番でコマンドを叩けないのでここから登録する
class AdminClientTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);
    }

    /**
     * @param string $email ログインさせるアドレス
     * @param bool $verified メール検証済みにするか
     * @return string アカウントID (ULID)
     */
    private function loginAs(string $email, bool $verified = true): string {
        $accounts = app(ChreeAccountRepository::class);
        $account = $accounts->create(AccountOrigin::USER, $email, 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        if ($verified) $accounts->markEmailVerified($account->id);

        $this->post('/login', ['email' => $email, 'password' => 'correct-horse']);

        return $account->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array {
        return [
            'name' => 'DokuFarm',
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback'],
            'scopes' => 'openid profile email',
            'trust' => 'official',
        ];
    }

    // 権限が無い相手には、そこに何かある事実も伏せる
    public function test_hidesAdminFromNonAdmins(): void {
        $this->loginAs('someone@example.com');

        $this->get('/admin/clients')->assertNotFound();
        $this->post('/admin/clients', $this->payload())->assertNotFound();
    }

    public function test_requiresLogin(): void {
        $this->get('/admin/clients')->assertRedirect('/login');
    }

    // 未検証のアドレスで管理者を名乗れると、先に登録するだけで入れてしまう
    public function test_rejectsUnverifiedAdminAddress(): void {
        $this->loginAs(self::ADMIN_EMAIL, verified: false);

        $this->get('/admin/clients')->assertNotFound();
    }

    public function test_allowsConfiguredAdmin(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->get('/admin/clients')->assertOk();
    }

    // /admin はシステム管理のトップ。利用者向けの設定とは入口を分けている
    public function test_adminRootShowsPanel(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->get('/admin')->assertOk();
    }

    public function test_hidesAdminPanelFromNonAdmins(): void {
        $this->loginAs('someone@example.com');

        $this->get('/admin')->assertNotFound();
    }

    public function test_registersClient(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/admin/clients', $this->payload())->assertRedirect('/admin/clients');

        $client = OAuthClientModel::query()->where('name', 'DokuFarm')->firstOrFail();
        $this->assertSame(ServiceTrust::OFFICIAL, $client->trust);
        $this->assertSame(['https://doku.example.com/auth/chreeid/callback'], $client->redirect_uris);
        $this->assertTrue($client->is_confidential);
        $this->assertNotNull($client->secret_hash);
    }

    // 平文の secret は登録直後の1回しか出せない
    public function test_showsSecretOnceAfterRegistering(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->post('/admin/clients', $this->payload());

        $this->assertIsArray(session('issuedSecret'));

        $this->get('/admin/clients')->assertOk();
        $this->get('/admin/clients')->assertOk();

        $this->assertNull(session('issuedSecret'));
    }

    public function test_updatesClient(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->post('/admin/clients', $this->payload());
        $client = OAuthClientModel::query()->firstOrFail();
        $secretBefore = $client->secret_hash;

        $this->post("/admin/clients/{$client->id}", [
            'name' => 'DokuFarm (改名)',
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback', 'http://dokufarm.test/auth/chreeid/callback'],
            'scopes' => 'openid email',
            'trust' => 'approved',
        ])->assertRedirect('/admin/clients');

        $client->refresh();
        $this->assertSame('DokuFarm (改名)', $client->name);
        $this->assertCount(2, $client->redirect_uris);
        $this->assertSame(ServiceTrust::APPROVED, $client->trust);
        // client_id と secret は据え置き。変わると接続中の RP が黙って止まる
        $this->assertSame($secretBefore, $client->secret_hash);
    }

    public function test_rotatesSecret(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->post('/admin/clients', $this->payload());
        $client = OAuthClientModel::query()->firstOrFail();
        $before = $client->secret_hash;

        $this->post("/admin/clients/{$client->id}/secret")->assertRedirect('/admin/clients');

        $client->refresh();
        $this->assertNotSame($before, $client->secret_hash);
        $this->assertIsArray(session('issuedSecret'));
    }

    public function test_deletesClient(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->post('/admin/clients', $this->payload());
        $client = OAuthClientModel::query()->firstOrFail();

        $this->post("/admin/clients/{$client->id}/delete")->assertRedirect('/admin/clients');

        $this->assertSame(0, OAuthClientModel::query()->count());
    }

    public function test_rejectsInvalidTrust(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/admin/clients', array_merge($this->payload(), ['trust' => 'superuser']))
            ->assertSessionHasErrors('trust');

        $this->assertSame(0, OAuthClientModel::query()->count());
    }

    public function test_rejectsNonUrlRedirect(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/admin/clients', array_merge($this->payload(), ['redirect_uris' => ['not-a-url']]))
            ->assertSessionHasErrors('redirect_uris.0');
    }

    public function test_registersPublicClientWithoutSecret(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/admin/clients', array_merge($this->payload(), ['is_confidential' => false]));

        $client = OAuthClientModel::query()->firstOrFail();
        $this->assertFalse($client->is_confidential);
        $this->assertNull($client->secret_hash);
    }
}
