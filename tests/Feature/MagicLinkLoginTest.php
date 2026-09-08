<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\EnableMagicLink;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Credential\Mail\MagicLinkMail;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// メールだけでログインする経路
class MagicLinkLoginTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('register');
        RateLimiter::clear('verify');
        RateLimiter::clear('challenge');
    }

    /**
     * @param bool $enabled メールログインを有効にするか
     * @return string アカウントID (ULID)
     */
    private function account(bool $enabled = true): string {
        $account = app(ChreeAccountRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        if ($enabled) app(EnableMagicLink::class)->execute($account->id);

        return $account->id;
    }

    /**
     * リンクを申し込み、メールに載るはずの平文トークンを組み立てる。
     *
     * @return string 平文トークン
     */
    private function requestLink(): string {
        $this->post('/login/magic', ['email' => 'user@example.com']);

        $token = Str::random(64);
        OneTimeTokenModel::query()
            ->where('purpose', OneTimeTokenModel::PURPOSE_LOGIN)
            ->update(['token_hash' => hash('sha256', $token)]);

        return $token;
    }

    public function test_showsRequestPage(): void {
        $this->get('/login/magic')->assertOk();
    }

    public function test_sendsLinkWhenEnabled(): void {
        $this->account();

        $this->post('/login/magic', ['email' => 'user@example.com'])
            ->assertRedirect('/login/magic/sent');

        Mail::assertSent(MagicLinkMail::class);
    }

    public function test_logsInFromLink(): void {
        $accountId = $this->account();
        $token = $this->requestLink();

        $this->get("/login/magic/{$token}")->assertRedirect('/');

        $this->assertSame($accountId, session('chreeid.account_id'));
    }

    public function test_linkWorksOnlyOnce(): void {
        $this->account();
        $token = $this->requestLink();

        $this->get("/login/magic/{$token}");
        $this->post('/logout');

        $this->get("/login/magic/{$token}")->assertOk();
        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_rejectsExpiredLink(): void {
        $this->account();
        $token = $this->requestLink();
        OneTimeTokenModel::query()->update(['expires_at' => now()->subMinute()]);

        $this->get("/login/magic/{$token}")->assertOk();
        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_rejectsUnknownLink(): void {
        $this->account();

        $this->get('/login/magic/' . Str::random(64))->assertOk();
        $this->assertNull(session('chreeid.account_id'));
    }

    // 有効化していないアカウントには送らない。応答は同じ
    public function test_doesNotSendWhenNotEnabled(): void {
        $this->account(enabled: false);

        $this->post('/login/magic', ['email' => 'user@example.com'])
            ->assertRedirect('/login/magic/sent');

        Mail::assertNothingSent();
        $this->assertSame(0, OneTimeTokenModel::query()->count());
    }

    public function test_unknownAddressLooksTheSame(): void {
        $this->post('/login/magic', ['email' => 'nobody@example.com'])
            ->assertRedirect('/login/magic/sent');

        Mail::assertNothingSent();
    }

    // メールを開けただけでは1要素。2FA を迂回できてはいけない
    public function test_doesNotBypassSecondFactor(): void {
        $accountId = $this->account();
        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($accountId, $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));

        $token = $this->requestLink();

        $this->get("/login/magic/{$token}")->assertRedirect('/login/challenge');
        $this->assertNull(session('chreeid.account_id'));

        $this->post('/login/challenge', ['code' => app(Totp::class)->at($secret, intdiv(time(), 30))])
            ->assertRedirect('/');
        $this->assertSame($accountId, session('chreeid.account_id'));
    }

    public function test_registrationEnablesMagicLink(): void {
        $this->post('/register', ['email' => 'new@example.com']);

        $token = Str::random(64);
        \App\Modules\Identity\Infrastructure\PendingRegistrationModel::query()
            ->update(['token_hash' => hash('sha256', $token)]);

        // 登録はパスワードを決めた時点で完了する
        $this->post('/register/complete', ['token' => $token, 'password' => 'correct-horse']);
        $this->post('/logout');

        Mail::fake();
        $this->post('/login/magic', ['email' => 'new@example.com']);

        Mail::assertSent(MagicLinkMail::class);
    }

    public function test_securityScreenEnablesMagicLink(): void {
        $this->account(enabled: false);
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->post('/security/magic-link')->assertRedirect('/settings/security');

        $this->post('/logout');
        $this->post('/login/magic', ['email' => 'user@example.com']);
        Mail::assertSent(MagicLinkMail::class);
    }
}
