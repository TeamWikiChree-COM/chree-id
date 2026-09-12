<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\StartPasskeyLogin;
use App\Modules\Credential\Application\StartPasskeyRegistration;
use App\Modules\Credential\Domain\CredentialRegistry;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyContext;
use App\Modules\Credential\Infrastructure\Verifiers\PasskeyVerifier;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * パスキー。実際の署名検証はブラウザと認証器が要るのでここでは扱えない。
 * チャレンジの組み立てと、不正な入力を弾くことを確認する。
 */
class PasskeyTest extends TestCase {
    use RefreshDatabase;

    /**
     * @return AuthIdentity
     */
    private function makeAccount(): AuthIdentity {
        return app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
    }

    /**
     * 別のホストで開いていると、ブラウザは options を受け取った時点で必ず断る。
     * 渡す前に弾いて、設定のずれだと分かる文言を返す
     */
    public function test_refusesWhenHostDoesNotMatchTheRpId(): void {
        $account = $this->makeAccount();
        $this->withSession(['chreeid.account_id' => $account->id]);

        // Host ヘッダだけでは getHost() が変わらないので、絶対 URL で叩く
        $response = $this->postJson('http://somewhere-else.test/security/passkey/options');

        $response->assertStatus(422)->assertJsonPath('error', 'rp_id_mismatch');
        $this->assertIsString($response->json('reason'));
    }

    /**
     * 素の 401 だけでは、クッキーが届いていないのかセッションが消えたのかが分からない
     */
    public function test_explainsWhenSignedOut(): void {
        $this->postJson('/security/passkey/options')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated')
            ->assertJsonStructure(['reason']);

        $this->postJson('/security/passkey/register', ['credential' => '{}'])
            ->assertStatus(401)
            ->assertJsonStructure(['reason']);
    }

    public function test_buildsRegistrationChallenge(): void {
        $account = $this->makeAccount();

        $options = app(StartPasskeyRegistration::class)->execute($account);

        $this->assertSame(32, strlen($options->challenge));
        $this->assertSame($account->id, $options->user->id);
        $this->assertSame(app(PasskeyContext::class)->rpId(), $options->rp->id);
    }

    /**
     * どのアカウントかは端末に選ばせる。メールを先に聞かずに済み、存在も漏れない
     */
    public function test_loginChallengeDoesNotNameCredentials(): void {
        $options = app(StartPasskeyLogin::class)->execute();

        $this->assertSame(32, strlen($options->challenge));
        $this->assertSame([], $options->allowCredentials);
        $this->assertSame(app(PasskeyContext::class)->rpId(), $options->rpId);
    }

    /**
     * チャレンジは毎回変える。使い回すとリプレイを許す
     */
    public function test_challengeIsFreshEachTime(): void {
        $start = app(StartPasskeyLogin::class);

        $this->assertNotSame($start->execute()->challenge, $start->execute()->challenge);
    }

    // 実行環境の APP_URL に寄せると CI で落ちるので、issuer をこの場で決めて確かめる
    public function test_rpIdComesFromIssuer(): void {
        Config::set('chreeid.issuer', 'https://id.example.com');

        $this->assertSame('id.example.com', app(PasskeyContext::class)->rpId());
    }

    public function test_originComesFromIssuer(): void {
        Config::set('chreeid.issuer', 'https://id.example.com/');

        $this->assertSame('https://id.example.com', app(PasskeyContext::class)->origin());
    }

    public function test_isRegisteredInRegistry(): void {
        $verifier = app(CredentialRegistry::class)->get(CredentialType::PASSKEY);

        $this->assertInstanceOf(PasskeyVerifier::class, $verifier);
    }

    /**
     * 端末の所持と生体認証で既に多要素なので、単独で認証を完了してよい
     */
    public function test_isSufficientAlone(): void {
        $verifier = app(CredentialRegistry::class)->get(CredentialType::PASSKEY);

        $this->assertNotNull($verifier);
        $this->assertTrue($verifier->isSufficient());
    }

    public function test_rejectsMalformedCredential(): void {
        $account = $this->makeAccount();
        $options = app(StartPasskeyLogin::class)->execute();

        $result = app(PasskeyVerifier::class)->verify($account->id, [
            'credential' => 'not-json',
            'options' => $options,
        ]);

        $this->assertFalse($result->isSuccess());
    }

    public function test_rejectsMissingOptions(): void {
        $account = $this->makeAccount();

        $result = app(PasskeyVerifier::class)->verify($account->id, ['credential' => '{}']);

        $this->assertFalse($result->isSuccess());
    }
}
