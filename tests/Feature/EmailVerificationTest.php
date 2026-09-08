<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\PendingEmailChangeModel;
use App\Modules\Identity\Mail\EmailChangeNoticeMail;
use App\Modules\Identity\Mail\VerifyEmailChangeMail;
use App\Modules\Identity\Mail\VerifyEmailMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// メールアドレスの確認と変更。
// 確認済みでないと ID Token の email_verified が偽になり、RP 側で引き継げない
class EmailVerificationTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('login');
        RateLimiter::clear('register');
        RateLimiter::clear('verify');
    }

    /**
     * 未検証のアカウントでログインする (メール確認より前に作られたアカウント相当)
     *
     * @return string アカウントID (ULID)
     */
    private function login(): string {
        $account = app(ChreeAccountRepository::class)
            ->create(AccountOrigin::USER, 'old@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'old@example.com', 'password' => 'correct-horse']);

        return $account->id;
    }

    /**
     * 発行済みトークンを差し替えて平文を得る
     *
     * @param string $purpose OneTimeTokenModel::PURPOSE_*
     * @return string 平文トークン
     */
    private function tokenFor(string $purpose): string {
        $token = Str::random(64);
        OneTimeTokenModel::query()
            ->where('purpose', $purpose)
            ->update(['token_hash' => hash('sha256', $token)]);

        return $token;
    }

    public function test_accountStartsUnverified(): void {
        $id = $this->login();

        $this->assertFalse(app(ChreeAccountRepository::class)->findById($id)?->isEmailVerified());
    }

    public function test_sendsVerificationMail(): void {
        $this->login();

        $this->post('/profile/email/verify')->assertRedirect('/settings');

        Mail::assertSent(VerifyEmailMail::class);
    }

    public function test_confirmsEmail(): void {
        $id = $this->login();
        $this->post('/profile/email/verify');

        $token = $this->tokenFor(OneTimeTokenModel::PURPOSE_VERIFY_EMAIL);
        $this->get("/profile/email/verify/{$token}")->assertRedirect('/settings');

        $this->assertTrue(app(ChreeAccountRepository::class)->findById($id)?->isEmailVerified());
    }

    public function test_verificationLinkWorksOnlyOnce(): void {
        $this->login();
        $this->post('/profile/email/verify');
        $token = $this->tokenFor(OneTimeTokenModel::PURPOSE_VERIFY_EMAIL);
        $this->get("/profile/email/verify/{$token}");

        $row = OneTimeTokenModel::query()->firstOrFail();
        $this->assertNotNull($row->used_at);
    }

    public function test_doesNotResendWhenAlreadyVerified(): void {
        $id = $this->login();
        app(ChreeAccountRepository::class)->markEmailVerified($id);

        $this->post('/profile/email/verify');

        Mail::assertNothingSent();
    }

    // --- 変更 ---

    public function test_doesNotChangeEmailUntilConfirmed(): void {
        $id = $this->login();

        $this->post('/profile/email/change', ['email' => 'new@example.com'])->assertRedirect('/settings');

        $this->assertSame('old@example.com', app(ChreeAccountRepository::class)->findById($id)?->email);
        Mail::assertSent(VerifyEmailChangeMail::class);
    }

    // 乗っ取りに気付ける唯一の経路なので、元のアドレスにも知らせる
    public function test_notifiesOldAddress(): void {
        $this->login();

        $this->post('/profile/email/change', ['email' => 'new@example.com']);

        Mail::assertSent(EmailChangeNoticeMail::class);
    }

    public function test_changesEmailOnConfirmation(): void {
        $id = $this->login();
        $this->post('/profile/email/change', ['email' => 'new@example.com']);

        $token = Str::random(64);
        PendingEmailChangeModel::query()->update(['token_hash' => hash('sha256', $token)]);

        $this->get("/profile/email/change/{$token}")->assertRedirect('/settings');

        $account = app(ChreeAccountRepository::class)->findById($id);
        $this->assertNotNull($account);
        $this->assertSame('new@example.com', $account->email);
        // 到達性が確かめられたので検証済みにもなる
        $this->assertTrue($account->isEmailVerified());
    }

    public function test_rejectsAddressUsedByAnotherAccount(): void {
        app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'taken@example.com', '他人');
        $this->login();

        $this->post('/profile/email/change', ['email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_rejectsExpiredChangeLink(): void {
        $id = $this->login();
        $this->post('/profile/email/change', ['email' => 'new@example.com']);

        $token = Str::random(64);
        PendingEmailChangeModel::query()->update([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get("/profile/email/change/{$token}")->assertRedirect('/settings');

        $this->assertSame('old@example.com', app(ChreeAccountRepository::class)->findById($id)?->email);
    }

    public function test_requiresLogin(): void {
        $this->post('/profile/email/verify')->assertRedirect('/login');
        $this->post('/profile/email/change', ['email' => 'new@example.com'])->assertRedirect('/login');
    }
}
