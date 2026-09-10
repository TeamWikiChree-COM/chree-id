<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Credential\Mail\PasswordResetMail;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// パスワード再設定。再設定してもログインはさせない
class PasswordResetTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('register');
        RateLimiter::clear('verify');
        RateLimiter::clear('login');
    }

    /**
     * @return string アカウントID (ULID)
     */
    private function account(): string {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'old-password');

        return $account->id;
    }

    /**
     * 再設定を申し込み、メールに載るはずの平文トークンを組み立てる。
     *
     * DB にはハッシュしか無いので、テスト側で作り直して差し替える。
     *
     * @return string 平文トークン
     */
    private function requestReset(): string {
        $this->post('/password/forgot', ['email' => 'user@example.com']);

        $token = Str::random(64);
        OneTimeTokenModel::query()
            ->where('purpose', OneTimeTokenModel::PURPOSE_PASSWORD_RESET)
            ->update(['token_hash' => hash('sha256', $token)]);

        return $token;
    }

    public function test_showsForgotPage(): void {
        $this->get('/password/forgot')->assertOk();
    }

    public function test_sendsResetMail(): void {
        $this->account();

        $this->post('/password/forgot', ['email' => 'user@example.com'])
            ->assertRedirect('/password/forgot/sent');

        Mail::assertSent(PasswordResetMail::class);
    }

    // 応答からアドレスの存在を推測させない
    public function test_unknownAddressLooksTheSame(): void {
        $this->post('/password/forgot', ['email' => 'nobody@example.com'])
            ->assertRedirect('/password/forgot/sent');

        Mail::assertNothingSent();
        $this->assertSame(0, OneTimeTokenModel::query()->count());
    }

    public function test_changesPassword(): void {
        $this->account();
        $token = $this->requestReset();

        $this->get("/password/reset/{$token}")->assertOk();
        $this->post('/password/reset', ['token' => $token, 'password' => 'brand-new-password'])
            ->assertRedirect('/login');

        // 再設定だけではログインしない
        $this->assertNull(session('chreeid.account_id'));

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'brand-new-password'])
            ->assertRedirect('/');
        $this->assertNotNull(session('chreeid.account_id'));
    }

    public function test_oldPasswordStopsWorking(): void {
        $this->account();
        $token = $this->requestReset();
        $this->post('/password/reset', ['token' => $token, 'password' => 'brand-new-password']);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'old-password'])
            ->assertSessionHasErrors('email');
        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_tokenWorksOnlyOnce(): void {
        $this->account();
        $token = $this->requestReset();
        $this->post('/password/reset', ['token' => $token, 'password' => 'brand-new-password']);

        $this->post('/password/reset', ['token' => $token, 'password' => 'another-password'])->assertOk();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'another-password'])
            ->assertSessionHasErrors('email');
    }

    public function test_rejectsExpiredToken(): void {
        $this->account();
        $token = $this->requestReset();
        OneTimeTokenModel::query()->update(['expires_at' => now()->subMinute()]);

        $this->get("/password/reset/{$token}")->assertOk();
        $this->post('/password/reset', ['token' => $token, 'password' => 'brand-new-password'])->assertOk();

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'brand-new-password'])
            ->assertSessionHasErrors('email');
    }

    public function test_rejectsUnknownToken(): void {
        $this->account();

        $this->post('/password/reset', ['token' => Str::random(64), 'password' => 'brand-new-password'])
            ->assertOk();
    }

    public function test_rejectsShortPassword(): void {
        $this->account();
        $token = $this->requestReset();

        $this->post('/password/reset', ['token' => $token, 'password' => 'short'])
            ->assertSessionHasErrors('password');
    }

    // 再設定で 2FA を迂回できてはいけない
    public function test_doesNotBypassSecondFactor(): void {
        $accountId = $this->account();
        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($accountId, $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));

        $token = $this->requestReset();
        $this->post('/password/reset', ['token' => $token, 'password' => 'brand-new-password']);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'brand-new-password'])
            ->assertRedirect('/login/challenge');
        $this->assertNull(session('chreeid.account_id'));
    }
}
