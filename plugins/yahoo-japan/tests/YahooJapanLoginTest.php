<?php
namespace Plugins\YahooJapan\Tests;

use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use App\Providers\PluginServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// Yahoo! JAPAN ID でのログイン (YConnect v2)
class YahooJapanLoginTest extends TestCase {
    use RefreshDatabase;

    private const CLIENT_ID = 'yj-client';

    #[Override]
    protected function setUp(): void {
        parent::setUp();

        // plugin.json では無効にしてあり、本体は読み込まない。テストでだけ読み込む
        $plugins = $this->app->make(PluginRegistry::class);
        $manifest = $plugins->find('yahoo-japan');
        $this->assertNotNull($manifest);
        $loader = $this->app->getProvider(PluginServiceProvider::class);
        $this->assertInstanceOf(PluginServiceProvider::class, $loader);
        $loader->load($plugins, $manifest);

        // 審査を通ったアプリの形を基本にする。UserInfo を使わない既定の形は、個別のテストで確かめる
        config(['yahoo-japan.client_id' => self::CLIENT_ID, 'yahoo-japan.client_secret' => 'secret', 'yahoo-japan.userinfo' => true]);
    }

    /**
     * ログインを始め、Yahoo! JAPAN へ送り出したときの state と nonce を返す。
     *
     * @return array{string, string}
     */
    private function start(): array {
        $location = (string) $this->get('/auth/yahoo-japan/redirect')->headers->get('Location');
        $this->assertStringStartsWith('https://auth.login.yahoo.co.jp/yconnect/v2/authorization?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertIsString($query['state'] ?? null);
        $this->assertIsString($query['nonce'] ?? null);

        return [$query['state'], $query['nonce']];
    }

    /**
     * @param array<string, mixed> $claims
     * @return string
     */
    private function idToken(array $claims): string {
        $encode = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return $encode('{"alg":"RS256"}') . '.' . $encode((string) json_encode($claims)) . '.sig';
    }

    /**
     * @param string $nonce
     * @param array<string, mixed> $userinfo
     */
    private function fakeYahoo(string $nonce, array $userinfo): void {
        Http::fake([
            'auth.login.yahoo.co.jp/yconnect/v2/token' => Http::response([
                'access_token' => 'at',
                'id_token' => $this->idToken([
                    'iss' => 'https://auth.login.yahoo.co.jp/yconnect/v2',
                    'aud' => [self::CLIENT_ID],
                    'sub' => 'YJ123',
                    'nonce' => $nonce,
                    'exp' => time() + 600,
                ]),
            ]),
            'userinfo.yahooapis.jp/*' => Http::response($userinfo),
        ]);
    }

    #[TestDox('設定が揃えばログイン画面に Yahoo! JAPAN の名前とアイコンで出す')]
    public function test_isUsableWhenConfigured(): void {
        $registry = app(ExternalIdpRegistry::class);
        $this->assertContains('yahoo-japan', $registry->usableNames());
        $display = $registry->displays('ja')['yahoo-japan'];
        $this->assertSame('Yahoo! JAPAN ID', $display['label']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', (string) $display['svg']);

        // シークレットはクライアントサイドのアプリには無いので、無くても使える
        config(['yahoo-japan.client_secret' => '']);
        $this->assertContains('yahoo-japan', $registry->usableNames());

        config(['yahoo-japan.client_id' => '']);
        $this->assertNotContains('yahoo-japan', $registry->usableNames());
    }

    #[TestDox('シークレットが無ければ、Basic 認証を付けず PKCE だけで交換する')]
    public function test_usesPkceWithoutSecret(): void {
        config(['yahoo-japan.client_secret' => '']);
        $location = (string) $this->get('/auth/yahoo-japan/redirect')->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertIsString($query['state'] ?? null);
        $this->assertIsString($query['nonce'] ?? null);
        $this->fakeYahoo($query['nonce'], ['sub' => 'YJ123', 'email' => 'taro@example.jp', 'email_verified' => true]);

        $this->get("/auth/yahoo-japan/callback?code=c&state={$query['state']}")->assertRedirect('/');

        Http::assertSent(static function ($request) use ($query): bool {
            if (!str_contains($request->url(), '/token')) return false;

            $challenge = rtrim(strtr(base64_encode(hash('sha256', (string) $request['code_verifier'], true)), '+/', '-_'), '=');

            return !$request->hasHeader('Authorization') && $request['client_id'] === self::CLIENT_ID && $challenge === $query['code_challenge'];
        });
    }

    #[TestDox('id_token と UserInfo から外部アカウントを作ってログインさせる')]
    public function test_logsIn(): void {
        [$state, $nonce] = $this->start();
        $this->fakeYahoo($nonce, ['sub' => 'YJ123', 'email' => 'taro@example.jp', 'email_verified' => true, 'name' => 'ヤフー太郎']);

        $this->get("/auth/yahoo-japan/callback?code=c&state={$state}")->assertRedirect('/');

        $accountId = session('chreeid.account_id');
        $this->assertIsString($accountId);
        $account = app(AuthIdentityRepository::class)->findById($accountId);
        $this->assertNotNull($account);
        $this->assertSame('taro@example.jp', $account->email);
        $this->assertSame('ヤフー太郎', $account->displayName);

        Http::assertSent(static fn ($request): bool => str_contains($request->url(), '/token') && $request->hasHeader('Authorization'));
    }

    #[TestDox('UserInfo を使わない設定なら、openid だけを求め、id_token の sub だけでメールの無いアカウントを作る')]
    public function test_logsInWithoutUserinfo(): void {
        config(['yahoo-japan.userinfo' => false]);
        $location = (string) $this->get('/auth/yahoo-japan/redirect')->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('openid', $query['scope'] ?? null);
        $this->assertIsString($query['state'] ?? null);
        $this->assertIsString($query['nonce'] ?? null);
        $this->fakeYahoo($query['nonce'], []);

        $this->get("/auth/yahoo-japan/callback?code=c&state={$query['state']}")->assertRedirect('/');

        $accountId = session('chreeid.account_id');
        $this->assertIsString($accountId);
        $this->assertNull(app(AuthIdentityRepository::class)->findById($accountId)?->email);
        Http::assertNotSent(static fn ($request): bool => str_contains($request->url(), 'userinfo.yahooapis.jp'));
    }

    #[TestDox('UserInfo の sub が id_token と違えば受けない')]
    public function test_rejectsMismatchedUserinfo(): void {
        [$state, $nonce] = $this->start();
        $this->fakeYahoo($nonce, ['sub' => 'someone-else', 'email' => 'taro@example.jp']);

        $this->get("/auth/yahoo-japan/callback?code=c&state={$state}")->assertRedirect('/login');
        $this->assertNull(session('chreeid.account_id'));
    }

    #[TestDox('nonce が違う id_token は受けない')]
    public function test_rejectsWrongNonce(): void {
        [$state] = $this->start();
        $this->fakeYahoo('another', ['sub' => 'YJ123']);

        $this->get("/auth/yahoo-japan/callback?code=c&state={$state}")->assertRedirect('/login');
        $this->assertNull(session('chreeid.account_id'));
    }
}
