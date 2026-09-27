<?php
namespace Plugins\Saml\Tests;

use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Plugins\Saml\Sp\SamlIdp;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

require_once __DIR__ . '/SamlResponseBuilder.php';

// 外部の SAML IdP でのログイン (ChreeID が SP 側)
class SamlLoginTest extends TestCase {
    use RefreshDatabase;

    private const SSO_URL = 'https://idp.example.com/sso';

    private SamlResponseBuilder $idp;

    /** @var array<string, mixed> */
    private array $server;

    #[Override]
    protected function setUp(): void {
        parent::setUp();

        $this->idp = new SamlResponseBuilder();
        $this->server = $_SERVER;

        config([
            'saml.idp.entity_id' => SamlResponseBuilder::IDP_ENTITY_ID,
            'saml.idp.sso_url' => self::SSO_URL,
            'saml.idp.x509cert' => $this->idp->certificate,
            'saml.idp.name_attribute' => 'displayName',
        ]);
    }

    #[Override]
    protected function tearDown(): void {
        $_SERVER = $this->server;

        parent::tearDown();
    }

    /**
     * ログインを始め、IdP へ送り出したときの RelayState と AuthnRequest の ID を返す。
     *
     * @return array{string, string}
     */
    private function start(): array {
        $location = (string) $this->get('/auth/saml/redirect')->headers->get('Location');
        $this->assertStringStartsWith(self::SSO_URL, $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $requestId = session(SamlIdp::REQUEST_ID);
        $this->assertIsString($requestId);
        $this->assertIsString($query['RelayState'] ?? null);

        return [$query['RelayState'], $requestId];
    }

    /**
     * IdP からの POST を受けさせ、回された先の URL を返す。
     *
     * @param string $samlResponse
     * @param string $relayState
     * @return string
     */
    private function receive(string $samlResponse, string $relayState): string {
        $location = (string) $this->post('/plugins/saml/acs', ['SAMLResponse' => $samlResponse, 'RelayState' => $relayState])
            ->headers->get('Location');
        $this->assertStringStartsWith(url('/plugins/saml/acs') . '?k=', $location);

        return $location;
    }

    /**
     * 回された GET を開く。
     *
     * php-saml は Destination を $_SERVER から組んだ今の URL と照らすが、
     * テストのリクエストは $_SERVER を埋めないので、ここで合わせる。
     *
     * @param string $location
     * @return TestResponse<Response>
     */
    private function land(string $location): TestResponse {
        $query = (string) parse_url($location, PHP_URL_QUERY);
        $https = parse_url(url('/'), PHP_URL_SCHEME) === 'https';
        // APP_URL にポートが付く環境 (CI は localhost:8000) でも同じ URL になるように、ポートも APP_URL から取る
        $port = parse_url(url('/'), PHP_URL_PORT) ?? ($https ? 443 : 80);

        $_SERVER['HTTP_HOST'] = (string) parse_url(url('/'), PHP_URL_HOST);
        $_SERVER['REQUEST_URI'] = '/plugins/saml/acs?' . $query;
        $_SERVER['QUERY_STRING'] = $query;
        $_SERVER['SERVER_PORT'] = (string) $port;
        if ($https) $_SERVER['HTTPS'] = 'on';
        else unset($_SERVER['HTTPS']);

        return $this->get($location);
    }

    /**
     * @param string $samlResponse
     * @param string $relayState
     * @return TestResponse<Response>
     */
    private function deliver(string $samlResponse, string $relayState): TestResponse {
        return $this->land($this->receive($samlResponse, $relayState));
    }

    /**
     * @param string $requestId
     * @param array<string, mixed> $overrides
     * @return string
     */
    private function response(string $requestId, array $overrides = []): string {
        return $this->idp->build($overrides + [
            'requestId' => $requestId,
            'nameId' => 'user@example.com',
            'acs' => url('/plugins/saml/acs'),
            'audience' => url('/plugins/saml/metadata'),
            'attributes' => ['displayName' => 'サムル太郎'],
        ]);
    }

    #[TestDox('設定が揃えばログイン画面に出す')]
    public function test_isUsableWhenConfigured(): void {
        $this->assertContains('saml', app(ExternalIdpRegistry::class)->usableNames());

        config(['saml.idp.x509cert' => '']);
        $this->assertNotContains('saml', app(ExternalIdpRegistry::class)->usableNames());
    }

    #[TestDox('ボタンの名前は設定から取り、無ければ SAML と出す')]
    public function test_sharesDisplayName(): void {
        $this->assertSame('SAML', app(ExternalIdpRegistry::class)->displays('ja')['saml']['label']);

        config(['saml.idp.label' => '株式会社サンプル']);
        $this->assertSame('株式会社サンプル', app(ExternalIdpRegistry::class)->displays('en')['saml']['label']);
    }

    #[TestDox('SP のメタデータを出す')]
    public function test_servesMetadata(): void {
        $this->get('/plugins/saml/metadata')
            ->assertOk()
            ->assertSee('entityID="' . url('/plugins/saml/metadata') . '"', false)
            ->assertSee(url('/plugins/saml/acs'), false);
    }

    #[TestDox('署名された応答なら、アカウントを作ってログインさせる')]
    public function test_logsInWithSignedResponse(): void {
        [$relayState, $requestId] = $this->start();

        $this->deliver($this->response($requestId), $relayState)->assertRedirect('/');

        $accountId = session('chreeid.account_id');
        $this->assertIsString($accountId);
        $account = app(AuthIdentityRepository::class)->findById($accountId);
        $this->assertNotNull($account);
        $this->assertSame('user@example.com', $account->email);
        $this->assertSame('サムル太郎', $account->displayName);
    }

    #[TestDox('署名の無い応答は受けない')]
    public function test_rejectsUnsignedResponse(): void {
        [$relayState, $requestId] = $this->start();

        $this->deliver($this->response($requestId, ['signed' => false]), $relayState)->assertRedirect('/login');
        $this->assertNull(session('chreeid.account_id'));
    }

    #[TestDox('別の鍵で署名された応答は受けない')]
    public function test_rejectsResponseSignedByAnotherKey(): void {
        [$relayState, $requestId] = $this->start();
        $this->idp = new SamlResponseBuilder('other');

        $this->deliver($this->response($requestId), $relayState)->assertRedirect('/login');
        $this->assertNull(session('chreeid.account_id'));
    }

    #[TestDox('こちらが出した要求への応答でなければ受けない')]
    public function test_rejectsResponseToAnotherRequest(): void {
        [$relayState] = $this->start();

        $this->deliver($this->response('_another'), $relayState)->assertRedirect('/login');
        $this->assertNull(session('chreeid.account_id'));
    }

    #[TestDox('RelayState が違えば受けない')]
    public function test_rejectsWrongRelayState(): void {
        [, $requestId] = $this->start();

        $this->deliver($this->response($requestId), 'forged')->assertRedirect('/login');
        $this->assertNull(session('chreeid.account_id'));
    }

    #[TestDox('transient の NameID では同じ人を結べないので受けない')]
    public function test_rejectsTransientNameId(): void {
        [$relayState, $requestId] = $this->start();

        $response = $this->response($requestId, ['nameIdFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient']);
        $this->deliver($response, $relayState)->assertRedirect('/login');
        $this->assertNull(session('chreeid.account_id'));
    }

    #[TestDox('メールを信頼しない設定では、同じメールの既存アカウントに紐付けない')]
    public function test_doesNotTakeOverExistingAccountByEmail(): void {
        $existing = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', '既存');
        [$relayState, $requestId] = $this->start();

        $this->deliver($this->response($requestId), $relayState)->assertRedirect('/login');
        $this->assertNotSame($existing->id, session('chreeid.account_id'));
    }

    #[TestDox('メールを信頼する設定なら、同じメールの既存アカウントに紐付ける')]
    public function test_linksExistingAccountWhenEmailIsTrusted(): void {
        config(['saml.idp.trust_email' => true]);
        $existing = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', '既存');
        [$relayState, $requestId] = $this->start();

        $this->deliver($this->response($requestId), $relayState)->assertRedirect('/');
        $this->assertSame($existing->id, session('chreeid.account_id'));
    }

    #[TestDox('預けた応答は一度しか使えない')]
    public function test_stashIsSingleUse(): void {
        [$relayState, $requestId] = $this->start();
        $location = $this->receive($this->response($requestId), $relayState);

        $this->land($location)->assertRedirect('/');
        $this->land($location)->assertRedirect('/login');
    }
}
