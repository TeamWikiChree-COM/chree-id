<?php
namespace Plugins\Saml\Tests;

use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Plugins\Saml\Idp\ServiceProviderModel;
use Tests\TestCase;

// SAML IdP の管理画面 (鍵と SP の登録)
class SamlAdminTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    private string $keyDir;

    #[Override]
    protected function setUp(): void {
        parent::setUp();

        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);

        // 本物の storage/saml を触らない
        $this->keyDir = sys_get_temp_dir() . '/chreeid-saml-' . bin2hex(random_bytes(6));
        config([
            'saml.signing.key_path' => $this->keyDir . '/idp.key',
            'saml.signing.certificate_path' => $this->keyDir . '/idp.crt',
        ]);

        OAuthClientModel::create([
            'id' => 'saml-sp',
            'name' => 'SAML のサービス',
            'redirect_uris' => [],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
        ]);
    }

    #[Override]
    protected function tearDown(): void {
        File::deleteDirectory($this->keyDir);

        parent::tearDown();
    }

    /**
     * @param string $email
     */
    private function loginAs(string $email): void {
        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, $email, 'テスト');
        $accounts->markEmailVerified($account->id);
        $this->withSession(['chreeid.account_id' => $account->id]);
    }

    /**
     * @param string $name fixtures/ の鍵の名前
     * @return string
     */
    private function fixture(string $name): string {
        return (string) file_get_contents(__DIR__ . "/fixtures/{$name}");
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function provider(array $overrides = []): array {
        return $overrides + [
            'client_id' => 'saml-sp',
            'entity_id' => 'https://sp.example.com/metadata',
            'acs_url' => 'https://sp.example.com/acs',
            'certificate' => '',
            'scopes' => 'openid email',
        ];
    }

    #[TestDox('運営でなければ、画面があることも見せない')]
    public function test_requiresAdmin(): void {
        $this->loginAs('user@example.com');

        $this->get('/plugins/saml/admin')->assertNotFound();
    }

    #[TestDox('管理画面のトップに入口を出す')]
    public function test_addsAdminEntry(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('plugins.0.href', '/plugins/saml/admin'));
    }

    #[TestDox('手元で作った鍵と証明書を取り込める')]
    public function test_importsKeyPair(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/plugins/saml/admin/key/import', ['private_key' => $this->fixture('idp.key'), 'certificate' => $this->fixture('idp.crt')])
            ->assertSessionHasNoErrors();

        $this->get('/plugins/saml/admin')->assertInertia(fn (Assert $page) => $page
            ->component('saml::Admin/Index')
            ->where('signingKey.subject', 'idp.example.com')
            ->where('keyUpdated', true));
    }

    #[TestDox('対になっていない鍵と証明書は取り込まない')]
    public function test_rejectsMismatchedPair(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/plugins/saml/admin/key/import', ['private_key' => $this->fixture('idp.key'), 'certificate' => $this->fixture('other.crt')])
            ->assertSessionHasErrors(['import' => 'key_pair_invalid']);
        $this->assertFileDoesNotExist($this->keyDir . '/idp.key');
    }

    #[TestDox('鍵があるときは、作り直しを明示しないと入れ替えない')]
    public function test_replacingKeyNeedsConfirmation(): void {
        $this->loginAs(self::ADMIN_EMAIL);
        $this->post('/plugins/saml/admin/key/import', ['private_key' => $this->fixture('idp.key'), 'certificate' => $this->fixture('idp.crt')]);

        $this->post('/plugins/saml/admin/key/import', ['private_key' => $this->fixture('other.key'), 'certificate' => $this->fixture('other.crt')]);
        $this->assertSame(trim($this->fixture('idp.crt')), trim((string) file_get_contents($this->keyDir . '/idp.crt')));

        $this->post('/plugins/saml/admin/key/import', ['private_key' => $this->fixture('other.key'), 'certificate' => $this->fixture('other.crt'), 'replace' => '1']);
        $this->assertSame(trim($this->fixture('other.crt')), trim((string) file_get_contents($this->keyDir . '/idp.crt')));
    }

    #[TestDox('SAML でつなぐサービスを登録・変更・削除できる')]
    public function test_managesServiceProviders(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/plugins/saml/admin/providers', $this->provider())->assertRedirect('/plugins/saml/admin');
        $sp = ServiceProviderModel::query()->where('client_id', 'saml-sp')->firstOrFail();

        $this->post("/plugins/saml/admin/providers/{$sp->id}", $this->provider(['certificate' => $this->fixture('other.crt')]))->assertRedirect('/plugins/saml/admin');
        $this->assertNotNull($sp->fresh()?->certificate);

        $this->post("/plugins/saml/admin/providers/{$sp->id}/delete")->assertRedirect('/plugins/saml/admin');
        $this->assertNull($sp->fresh());
        $this->assertNotNull(OAuthClientModel::query()->find('saml-sp'));
    }

    #[TestDox('誤った入力は項目ごとのコードで返す')]
    public function test_rejectsInvalidProvider(): void {
        $this->loginAs(self::ADMIN_EMAIL);

        $this->post('/plugins/saml/admin/providers', $this->provider([
            'client_id' => 'nothing',
            'acs_url' => 'http://sp.example.com/acs',
            'certificate' => 'not a certificate',
        ]))->assertSessionHasErrors([
            'client_id' => 'client_unknown',
            'acs_url' => 'acs_url_invalid',
            'certificate' => 'certificate_invalid',
        ]);

        $this->post('/plugins/saml/admin/providers', $this->provider(['scopes' => 'openid admin']))
            ->assertSessionHasErrors(['scopes' => 'scopes_not_allowed']);

        $this->assertSame(0, ServiceProviderModel::query()->count());
    }
}
