<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\GenerateRecoveryCodes;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// 2FA を有効にしたアカウントのログイン。二要素目は /login/challenge で受ける
class TwoFactorLoginTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('challenge');
        RateLimiter::clear('login');
    }

    /**
     * パスワードと TOTP を持つアカウントを作る。
     *
     * @return array{id: string, secret: string}
     */
    private function account(): array {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($account->id, $secret, $this->codeFor($secret));

        return ['id' => $account->id, 'secret' => $secret];
    }

    /**
     * @param string $secret TOTP の秘密鍵
     * @return string いまの時間枠のコード
     */
    private function codeFor(string $secret): string {
        return app(Totp::class)->at($secret, intdiv(time(), 30));
    }

    /**
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function login(): \Illuminate\Testing\TestResponse {
        return $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);
    }

    public function test_passwordAloneDoesNotLogIn(): void {
        $this->account();

        $this->login()->assertRedirect('/login/challenge');

        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_completesWithTotpCode(): void {
        $account = $this->account();
        $this->login();

        $this->post('/login/challenge', ['code' => $this->codeFor($account['secret'])])
            ->assertRedirect('/');

        $this->assertSame($account['id'], session('chreeid.account_id'));
    }

    public function test_rejectsWrongTotpCode(): void {
        $this->account();
        $this->login();

        $this->post('/login/challenge', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_completesWithRecoveryCode(): void {
        $account = $this->account();
        $codes = app(GenerateRecoveryCodes::class)->execute($account['id']);
        $this->login();

        $this->post('/login/challenge', ['code' => $codes[0], 'useRecoveryCode' => true])
            ->assertRedirect('/');

        $this->assertSame($account['id'], session('chreeid.account_id'));
    }

    public function test_challengeRequiresPasswordFirst(): void {
        $this->account();

        $this->get('/login/challenge')->assertRedirect('/login');
        $this->post('/login/challenge', ['code' => '000000'])->assertRedirect('/login');
    }

    // 一次認証を通しただけの状態で他の画面に入れてはいけない
    public function test_pendingStateIsNotLoggedIn(): void {
        $this->account();
        $this->login();

        $this->get('/')->assertRedirect('/login');
        $this->get('/settings/security')->assertRedirect('/login');
    }

    public function test_cancelDiscardsPendingState(): void {
        $this->account();
        $this->login();

        $this->post('/login/challenge/cancel')->assertRedirect('/login');
        $this->get('/login/challenge')->assertRedirect('/login');
    }

    // 2FA を設定していないアカウントは今までどおり1段で入れる
    public function test_accountWithoutTotpLogsInDirectly(): void {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'plain@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'plain@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/');

        $this->assertSame($account->id, session('chreeid.account_id'));
    }
}
