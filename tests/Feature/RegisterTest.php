<?php
namespace Tests\Feature;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\PendingRegistrationModel;
use App\Modules\Identity\Mail\RegistrationExistsMail;
use App\Modules\Identity\Mail\VerifyRegistrationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// アカウント登録。申し込みはメールアドレスだけで、パスワードはリンクを開いたあとに決める
class RegisterTest extends TestCase {
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
     * 申し込みを出し、メールに載るはずの平文トークンを組み立てる。
     *
     * DB にはハッシュしか無いので、テスト側でも同じ乱数は得られない。
     * ここではトークンを自前で作り直して差し替える。
     *
     * @param string $email 申し込むメールアドレス
     * @return string 確認 URL に載せる平文トークン
     */
    private function requestRegistration(string $email = 'new@example.com'): string {
        $this->post('/register', ['email' => $email]);

        $token = Str::random(64);
        PendingRegistrationModel::query()
            ->where('email', $email)
            ->update(['token_hash' => hash('sha256', $token)]);

        return $token;
    }

    /**
     * パスワードを決めてアカウント作成まで進める。
     *
     * @param string $token 平文トークン
     * @param string $password 決めるパスワード
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function completeWith(string $token, string $password = 'correct-horse'): TestResponse {
        return $this->post('/register/complete', ['token' => $token, 'password' => $password]);
    }

    public function test_showsRegisterPage(): void {
        $this->get('/register')->assertOk();
    }

    public function test_doesNotCreateAccountBeforeVerification(): void {
        $this->post('/register', ['email' => 'new@example.com'])->assertRedirect('/register/sent');

        $this->assertNull(app(ChreeAccountRepository::class)->findByEmail('new@example.com'));
        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_sendsVerificationMail(): void {
        $this->post('/register', ['email' => 'new@example.com']);

        Mail::assertSent(VerifyRegistrationMail::class);
    }

    // 申し込みの時点で資格情報を預からない。
    // 預かると、第三者が申し込んだパスワードのままアカウントが作られてしまう
    public function test_doesNotStoreCredentialsBeforeVerification(): void {
        $this->post('/register', [
            'email' => 'new@example.com',
            'password' => 'attacker-chosen',
            'display_name' => '攻撃者が決めた名前',
        ]);

        $pending = PendingRegistrationModel::query()->firstOrFail();
        $columns = array_keys($pending->getAttributes());

        $this->assertNotContains('password_hash', $columns);
        $this->assertNotContains('display_name', $columns);
    }

    public function test_verifyShowsPasswordFormWithoutCreating(): void {
        $token = $this->requestRegistration();

        $this->get("/register/verify/{$token}")->assertOk();

        $this->assertNull(app(ChreeAccountRepository::class)->findByEmail('new@example.com'));
        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_createsAccountAndLogsInAfterSettingPassword(): void {
        $token = $this->requestRegistration();

        $this->completeWith($token)->assertRedirect('/');

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);
        $this->assertSame(AccountOrigin::USER, $account->origin);
        // 表示名は登録では受け取らない。/profile であとから設定する
        $this->assertNull($account->displayName);
        $this->assertSame($account->id, session('chreeid.account_id'));
    }

    public function test_chosenPasswordWorksForLogin(): void {
        $token = $this->requestRegistration();
        $this->completeWith($token, 'brand-new-password');
        $this->post('/logout');

        $this->post('/login', ['email' => 'new@example.com', 'password' => 'brand-new-password'])
            ->assertRedirect('/');
        $this->assertNotNull(session('chreeid.account_id'));
    }

    public function test_marksEmailVerified(): void {
        $token = $this->requestRegistration();
        $this->completeWith($token);

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);
        $this->assertTrue($account->isEmailVerified());
    }

    public function test_setsPasswordCredential(): void {
        $token = $this->requestRegistration();
        $this->completeWith($token);

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);

        $this->assertSame(1, CredentialModel::query()
            ->where('chree_account_id', $account->id)
            ->where('type', CredentialType::PASSWORD)
            ->count());
    }

    public function test_consumesTokenOnce(): void {
        $token = $this->requestRegistration();
        $this->completeWith($token);
        $this->post('/logout');

        $this->completeWith($token, 'another-password')->assertOk();

        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_rejectsExpiredToken(): void {
        $token = $this->requestRegistration();
        PendingRegistrationModel::query()->update(['expires_at' => now()->subMinute()]);

        $this->get("/register/verify/{$token}")->assertOk();
        $this->completeWith($token)->assertOk();

        $this->assertNull(app(ChreeAccountRepository::class)->findByEmail('new@example.com'));
    }

    public function test_rejectsUnknownToken(): void {
        $this->completeWith(Str::random(64))->assertOk();

        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_rejectsShortPassword(): void {
        $token = $this->requestRegistration();

        $this->completeWith($token, 'short')->assertSessionHasErrors('password');

        $this->assertNull(app(ChreeAccountRepository::class)->findByEmail('new@example.com'));
    }

    // 応答からアドレスの存在を推測させないため、登録済みでも画面は同じ
    public function test_hidesExistingEmailBehindSameResponse(): void {
        app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'new@example.com', '既存');

        $this->post('/register', ['email' => 'new@example.com'])->assertRedirect('/register/sent');

        Mail::assertSent(RegistrationExistsMail::class);
        Mail::assertNotSent(VerifyRegistrationMail::class);
        $this->assertSame(0, PendingRegistrationModel::query()->count());
    }

    public function test_dashboardRequiresLogin(): void {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_dashboardShowsAccountAfterRegistration(): void {
        $token = $this->requestRegistration();
        $this->completeWith($token);

        $this->get('/')->assertOk();
    }
}
