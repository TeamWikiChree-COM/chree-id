<?php
namespace Tests\Feature;

use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\ExternalLogin\Infrastructure\GitHubIdp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

// GitHub ログイン (ChreeID が RP 側)。GitHub は OIDC ではないので API を叩いて本人を知る
class GitHubLoginTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();

        Config::set('services.github.client_id', 'gh-client');
        Config::set('services.github.client_secret', 'gh-secret');
        Config::set('chreeid.issuer', 'https://id.example.com');
    }

    /**
     * @return GitHubIdp
     */
    private function idp(): GitHubIdp {
        return app(GitHubIdp::class);
    }

    /**
     * GitHub の応答をまとめて差し替える。
     *
     * @param array<mixed> $user /user の応答
     * @param array<mixed>|null $emails /user/emails の応答。null なら 404 を返す
     * @param array<mixed>|null $token トークン交換の応答
     * @return void
     */
    private function fakeGitHub(array $user, ?array $emails = null, ?array $token = null): void {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response($token ?? ['access_token' => 'gho_x']),
            'api.github.com/user/emails' => $emails === null
                ? Http::response([], 404)
                : Http::response($emails),
            'api.github.com/user' => Http::response($user),
        ]);
    }

    public function test_isRegisteredInTheIdpRegistry(): void {
        $this->assertContains('github', app(ExternalIdpRegistry::class)->names());
        $this->assertContains('github', app(ExternalIdpRegistry::class)->usableNames());
    }

    // 設定が無い IdP のボタンは出さない。押しても何も起きないため
    public function test_isNotUsableWithoutCredentials(): void {
        Config::set('services.github.client_id', null);
        Config::set('services.github.client_secret', null);

        $this->assertNotContains('github', app(ExternalIdpRegistry::class)->usableNames());
    }

    // ログインに要る分だけ求める。repo のような強い権限は取らない
    public function test_asksForTheMinimumScope(): void {
        $url = $this->idp()->authorizationUrl('state-1', 'unused');

        $this->assertStringContainsString('scope=read%3Auser+user%3Aemail', $url);
        $this->assertStringContainsString('state=state-1', $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode('https://id.example.com/auth/github/callback'), $url);
        $this->assertStringNotContainsString('repo', $url);
    }

    public function test_readsTheAccountFromTheApi(): void {
        $this->fakeGitHub(
            user: ['id' => 4242, 'login' => 'octocat', 'name' => 'オクト猫', 'email' => 'public@example.com'],
            emails: [['email' => 'primary@example.com', 'primary' => true, 'verified' => true]],
        );

        $identity = $this->idp()->exchange('code-1', 'unused');

        $this->assertSame('github', $identity->provider);
        // id は数値で返るが、identifier は文字列で持つ
        $this->assertSame('4242', $identity->subject);
        $this->assertSame('github:4242', $identity->credentialIdentifier());
        $this->assertSame('オクト猫', $identity->displayName);
        $this->assertSame('primary@example.com', $identity->email);
        $this->assertTrue($identity->emailVerified);
    }

    // **乗っ取り経路。** /user の email は公開プロフィールの値で検証済みとは限らない。
    // 検証済みとして渡すと、被害者のアドレスで作った GitHub アカウントから既存の ChreeID に入れる
    public function test_neverTrustsTheProfileEmail(): void {
        $this->fakeGitHub(
            user: ['id' => 1, 'login' => 'octocat', 'name' => null, 'email' => 'victim@example.com'],
            emails: null,
        );

        $identity = $this->idp()->exchange('code-1', 'unused');

        $this->assertSame('victim@example.com', $identity->email);
        $this->assertFalse($identity->emailVerified);
    }

    // 未検証のものを掴まない
    public function test_ignoresAnUnverifiedPrimaryAddress(): void {
        $this->fakeGitHub(
            user: ['id' => 1, 'login' => 'octocat', 'name' => null],
            emails: [['email' => 'unverified@example.com', 'primary' => true, 'verified' => false]],
        );

        $identity = $this->idp()->exchange('code-1', 'unused');

        $this->assertFalse($identity->emailVerified);
    }

    // primary でないものを連絡先にしない
    public function test_picksThePrimaryAddress(): void {
        $this->fakeGitHub(
            user: ['id' => 1, 'login' => 'octocat', 'name' => null],
            emails: [
                ['email' => 'other@example.com', 'primary' => false, 'verified' => true],
                ['email' => 'primary@example.com', 'primary' => true, 'verified' => true],
            ],
        );

        $identity = $this->idp()->exchange('code-1', 'unused');

        $this->assertSame('primary@example.com', $identity->email);
        $this->assertTrue($identity->emailVerified);
    }

    // 表示名を設定していない人は login で代用する
    public function test_fallsBackToTheLoginName(): void {
        $this->fakeGitHub(
            user: ['id' => 1, 'login' => 'octocat', 'name' => null],
            emails: [['email' => 'a@example.com', 'primary' => true, 'verified' => true]],
        );

        $this->assertSame('octocat', $this->idp()->exchange('code-1', 'unused')->displayName);
    }

    // **GitHub は交換に失敗しても 200 を返す。** 本文を見ないと通ってしまう
    public function test_detectsAFailedExchangeThatReturnsOk(): void {
        $this->fakeGitHub(
            user: ['id' => 1],
            emails: [],
            token: ['error' => 'bad_verification_code'],
        );

        $this->expectException(RuntimeException::class);
        $this->idp()->exchange('stale-code', 'unused');
    }
}
