<?php
namespace Plugins\Saml\Tests;

use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Plugins\Saml\Idp\ServiceProviderModel;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

require_once __DIR__ . '/SamlSpClient.php';

// ChreeID が SAML IdP として、SAML のサービス (SP) にログインさせる
class SamlIdpTest extends TestCase {
    use RefreshDatabase;

    private SamlSpClient $sp;
    private ServiceProviderModel $registered;

    /** @var array<string, mixed> */
    private array $server;

    #[Override]
    protected function setUp(): void {
        parent::setUp();

        $this->server = $_SERVER;
        $this->sp = new SamlSpClient();
        config([
            'saml.signing.key_path' => __DIR__ . '/fixtures/idp.key',
            'saml.signing.certificate_path' => __DIR__ . '/fixtures/idp.crt',
        ]);

        OAuthClientModel::create([
            'id' => 'saml-sp',
            'name' => 'SAML のサービス',
            'redirect_uris' => [],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'skips_consent' => true,
        ]);
        $this->registered = ServiceProviderModel::query()->create([
            'client_id' => 'saml-sp',
            'entity_id' => SamlSpClient::ENTITY_ID,
            'acs_url' => SamlSpClient::ACS_URL,
            'scopes' => 'openid email profile',
        ]);
    }

    #[Override]
    protected function tearDown(): void {
        $_SERVER = $this->server;

        parent::tearDown();
    }

    /**
     * @return string ログインさせた認証主体の ID
     */
    private function login(): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'サムル花子');
        app(UserAccounts::class)->ensure($account->id);
        $this->withSession(['chreeid.account_id' => $account->id]);

        return $account->id;
    }

    /**
     * リダイレクトをたどり、最後の画面を返す。
     *
     * @param string $url
     * @return TestResponse<Response>
     */
    private function follow(string $url): TestResponse {
        $response = $this->get($url);
        while ($response->isRedirect()) $response = $this->get((string) $response->headers->get('Location'));

        return $response;
    }

    /**
     * @param TestResponse<Response> $page
     * @return array{action: string, fields: array<string, string>}
     */
    private function postBinding(TestResponse $page): array {
        $page->assertInertia(fn (Assert $p) => $p->component('saml::PostBinding'));

        /** @var array{action: string, fields: array<string, string>} */
        return $page->viewData('page')['props'];
    }

    #[TestDox('IdP のメタデータに entityID、SSO の受け口、署名の証明書を載せる')]
    public function test_servesMetadata(): void {
        $body = str_replace(["\n", "\r", '-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', (string) file_get_contents(__DIR__ . '/fixtures/idp.crt'));

        $this->get('/plugins/saml/idp/metadata')
            ->assertOk()
            ->assertSee('entityID="' . url('/plugins/saml/idp/metadata') . '"', false)
            ->assertSee('Location="' . url('/plugins/saml/idp/sso') . '"', false)
            ->assertSee($body, false);
    }

    #[TestDox('HTTP-Redirect の要求に、SP が受け取れる署名済みの Response を返す')]
    public function test_answersRedirectBinding(): void {
        $accountId = $this->login();

        $page = $this->follow('/plugins/saml/idp/sso?' . $this->sp->redirectQuery($this->sp->authnRequest(), 'back-to-page'));
        $post = $this->postBinding($page);

        $this->assertSame(SamlSpClient::ACS_URL, $post['action']);
        $this->assertSame('back-to-page', $post['fields']['RelayState']);

        $response = $this->sp->receive($post['fields']['SAMLResponse'], url('/plugins/saml/idp/metadata'), (string) file_get_contents(__DIR__ . '/fixtures/idp.crt'));
        $this->assertTrue($response->isValid($this->sp->requestId), (string) $response->getError(false));

        $serviceAccount = ServiceAccountModel::query()->where('auth_identity_id', $accountId)->where('client_id', 'saml-sp')->firstOrFail();
        $this->assertSame($serviceAccount->sub, $response->getNameId());
        $this->assertSame(['user@example.com'], $response->getAttributes()['email']);
    }

    #[TestDox('HTTP-POST の要求は、預けて GET に回してから進める')]
    public function test_acceptsPostBinding(): void {
        $this->login();

        $location = (string) $this->post('/plugins/saml/idp/sso', ['SAMLRequest' => base64_encode($this->sp->authnRequest()), 'RelayState' => 'x'])
            ->headers->get('Location');
        $this->assertStringStartsWith(url('/plugins/saml/idp/sso') . '?k=', $location);

        $this->postBinding($this->follow($location));
    }

    #[TestDox('登録されていない SP の要求は受けない')]
    public function test_rejectsUnknownSp(): void {
        $query = $this->sp->redirectQuery($this->sp->authnRequest(SamlSpClient::ACS_URL, 'https://evil.example.com'), '');

        $this->get('/plugins/saml/idp/sso?' . $query)->assertInertia(fn (Assert $p) => $p->component('saml::Error')->where('code', 'unknown_sp'));
    }

    #[TestDox('登録した ACS と違う送り先は受けない')]
    public function test_rejectsOtherAcs(): void {
        $query = $this->sp->redirectQuery($this->sp->authnRequest('https://evil.example.com/acs'), '');

        $this->get('/plugins/saml/idp/sso?' . $query)->assertInertia(fn (Assert $p) => $p->component('saml::Error')->where('code', 'acs_mismatch'));
    }

    #[TestDox('証明書を登録した SP は、正しく署名された要求だけを受ける')]
    public function test_requiresSignatureWhenCertificateIsRegistered(): void {
        $this->registered->update(['certificate' => $this->sp->certificate()]);
        $xml = $this->sp->authnRequest();

        foreach ([null, 'idp'] as $wrong) {
            $this->get('/plugins/saml/idp/sso?' . $this->sp->redirectQuery($xml, 'r', $wrong))
                ->assertInertia(fn (Assert $p) => $p->component('saml::Error')->where('code', 'bad_signature'));
        }

        $this->get('/plugins/saml/idp/sso?' . $this->sp->redirectQuery($xml, 'r', 'other'))->assertRedirect();
    }

    #[TestDox('本体での手続きを経ずに戻り先を開いても、Response は出さない')]
    public function test_resumeRequiresContext(): void {
        $this->login();

        $this->get('/plugins/saml/idp/resume?ctx=forged&grant=forged')
            ->assertInertia(fn (Assert $p) => $p->component('saml::Error')->where('code', 'expired'));
    }
}
