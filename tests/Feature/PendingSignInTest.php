<?php
namespace Tests\Feature;

use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Plugin\Application\PluginApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// プラグインから頼まれたサービスへのサインイン (PluginApi::authorizeService)
class PendingSignInTest extends TestCase {
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void {
        parent::setUp();

        // SAML などのプラグインの代わり。頼む側と、引換券を受け取る側
        Route::middleware('web')->get('/plugins/test/start', static fn (Request $request, PluginApi $api) => $api->authorizeService(
            $request->string('client')->toString(),
            ['openid', 'email'],
            url('/plugins/test/return'),
        ));
        Route::middleware('web')->get('/plugins/test/return', static function (Request $request, PluginApi $api): JsonResponse {
            $signIn = $api->takeServiceSignIn($request->string('grant')->toString());

            return response()->json(['signIn' => $signIn === null ? null : ['clientId' => $signIn->clientId, 'sub' => $signIn->subject, 'claims' => $signIn->claims]]);
        });
    }

    /**
     * @param ServiceTrust $trust
     * @return OAuthClientModel
     */
    private function makeClient(ServiceTrust $trust = ServiceTrust::OFFICIAL): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => 'sp-' . $trust->value,
            'name' => 'SAML のサービス',
            'redirect_uris' => [],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => $trust,
            'skips_consent' => $trust === ServiceTrust::OFFICIAL,
        ]);
    }

    /**
     * @return AuthIdentity
     */
    private function makeAccount(): AuthIdentity {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        app(UserAccounts::class)->ensure($account->id);

        return $account;
    }

    /**
     * @return string 戻り先の URL (引換券付き)
     */
    private function followToReturn(string $location): string {
        while (!str_starts_with($location, url('/plugins/test/return'))) {
            $response = $this->get($location);
            $response->assertRedirect();
            $location = (string) $response->headers->get('Location');
        }

        return $location;
    }

    #[TestDox('未ログインならログインさせ、済んだら続きから進めて引換券で sub と属性を渡す')]
    public function test_logsInThenReturnsGrant(): void {
        $client = $this->makeClient();
        $this->makeAccount();

        $pending = (string) $this->get('/plugins/test/start?client=' . $client->id)->headers->get('Location');
        $this->get($pending)->assertRedirect('/login');

        $afterLogin = (string) $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])->headers->get('Location');
        $this->assertSame($pending, $afterLogin);

        $result = $this->get($this->followToReturn($afterLogin))->json('signIn');

        $serviceAccount = ServiceAccountModel::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame($client->id, $result['clientId']);
        $this->assertSame($serviceAccount->sub, $result['sub']);
        $this->assertSame('user@example.com', $result['claims']['email']);
    }

    #[TestDox('同意が要るサービスなら同意画面を出し、許可されたら戻す')]
    public function test_asksForConsent(): void {
        $client = $this->makeClient(ServiceTrust::APPROVED);
        $account = $this->makeAccount();
        $this->withSession(['chreeid.account_id' => $account->id]);

        $pending = (string) $this->get('/plugins/test/start?client=' . $client->id)->headers->get('Location');
        $this->get($pending)->assertInertia(fn (Assert $page) => $page
            ->component('Oauth/Consent')
            ->where('action', parse_url($pending, PHP_URL_PATH) . '/approve'));

        $location = (string) $this->post($pending . '/approve')->headers->get('Location');
        $this->assertStringStartsWith(url('/plugins/test/return') . '?grant=', $location);
    }

    #[TestDox('引換券は一度しか使えない')]
    public function test_grantIsSingleUse(): void {
        $client = $this->makeClient();
        $account = $this->makeAccount();
        $this->withSession(['chreeid.account_id' => $account->id]);

        $return = $this->followToReturn((string) $this->get('/plugins/test/start?client=' . $client->id)->headers->get('Location'));

        $this->assertNotNull($this->get($return)->json('signIn'));
        $this->assertNull($this->get($return)->json('signIn'));
    }

    #[TestDox('途中で停止されたサービスには進めない')]
    public function test_stopsWhenServiceIsSuspended(): void {
        $client = $this->makeClient();
        $account = $this->makeAccount();
        $this->withSession(['chreeid.account_id' => $account->id]);

        $pending = (string) $this->get('/plugins/test/start?client=' . $client->id)->headers->get('Location');
        $client->update(['trust' => ServiceTrust::DISABLED]);

        $this->get($pending)->assertInertia(fn (Assert $page) => $page
            ->component('Oauth/Error')
            ->where('error', 'unauthorized_client'));
    }

    #[TestDox('知らない預かりは進めない')]
    public function test_rejectsUnknownPending(): void {
        $this->get('/authorize/pending/unknown')->assertInertia(fn (Assert $page) => $page
            ->component('Oauth/Error')
            ->where('error', 'invalid_request'));
    }

    #[TestDox('戻り先はプラグインのルートに限る')]
    public function test_rejectsReturnUrlOutsidePlugins(): void {
        $client = $this->makeClient();

        $this->expectException(InvalidArgumentException::class);
        app(PluginApi::class)->authorizeService($client->id, ['openid'], 'https://evil.example.com/');
    }

    #[TestDox('サービスに許していない範囲は頼めない')]
    public function test_rejectsScopesNotAllowed(): void {
        $client = $this->makeClient();

        $this->expectException(InvalidArgumentException::class);
        app(PluginApi::class)->authorizeService($client->id, ['openid', 'admin'], url('/plugins/test/return'));
    }
}
