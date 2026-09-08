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
use Illuminate\Support\Str;
use Tests\TestCase;

// アカウント登録。確認メールのリンクを踏むまでアカウントは作られない
class RegisterTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array {
        return [
            'email' => 'new@example.com',
            'display_name' => '新規ユーザー',
            'password' => 'correct-horse',
        ];
    }

    /**
     * 申し込みを出し、メールに載るはずの平文トークンを組み立てる。
     *
     * DB にはハッシュしか無いので、テスト側でも同じ乱数は得られない。
     * ここではトークンを自前で作り直して差し替える。
     *
     * @param array<string, string>|null $payload
     * @return string 確認 URL に載せる平文トークン
     */
    private function requestRegistration(?array $payload = null): string {
        $this->post('/register', $payload ?? $this->payload());

        $token = Str::random(64);
        PendingRegistrationModel::query()
            ->where('email', ($payload ?? $this->payload())['email'])
            ->update(['token_hash' => hash('sha256', $token)]);

        return $token;
    }

    public function test_showsRegisterPage(): void {
        $this->get('/register')->assertOk();
    }

    public function test_doesNotCreateAccountBeforeVerification(): void {
        $this->post('/register', $this->payload())->assertRedirect('/register/sent');

        $this->assertNull(app(ChreeAccountRepository::class)->findByEmail('new@example.com'));
        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_sendsVerificationMail(): void {
        $this->post('/register', $this->payload());

        Mail::assertSent(VerifyRegistrationMail::class);
    }

    public function test_createsAccountAndLogsInOnVerification(): void {
        $token = $this->requestRegistration();

        $this->get("/register/verify/{$token}")->assertRedirect('/');

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);
        $this->assertSame(AccountOrigin::USER, $account->origin);
        $this->assertSame('新規ユーザー', $account->displayName);
        $this->assertSame($account->id, session('chreeid.account_id'));
    }

    public function test_marksEmailVerifiedOnVerification(): void {
        $token = $this->requestRegistration();
        $this->get("/register/verify/{$token}");

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);
        $this->assertTrue($account->isEmailVerified());
    }

    public function test_setsPasswordCredentialOnVerification(): void {
        $token = $this->requestRegistration();
        $this->get("/register/verify/{$token}");

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);

        $this->assertSame(1, CredentialModel::query()
            ->where('chree_account_id', $account->id)
            ->where('type', CredentialType::PASSWORD)
            ->count());
    }

    public function test_allowsRegistrationWithoutDisplayName(): void {
        $token = $this->requestRegistration(['email' => 'new@example.com', 'password' => 'correct-horse']);

        $this->get("/register/verify/{$token}")->assertRedirect('/');

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);
        $this->assertNull($account->displayName);
    }

    public function test_treatsBlankDisplayNameAsUnset(): void {
        $token = $this->requestRegistration(
            array_merge($this->payload(), ['display_name' => '   ']),
        );
        $this->get("/register/verify/{$token}");

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);
        $this->assertNull($account->displayName);
    }

    public function test_consumesTokenOnce(): void {
        $token = $this->requestRegistration();
        $this->get("/register/verify/{$token}");

        $this->post('/logout');
        $this->get("/register/verify/{$token}")->assertOk();

        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_rejectsExpiredToken(): void {
        $token = $this->requestRegistration();
        PendingRegistrationModel::query()->update(['expires_at' => now()->subMinute()]);

        $this->get("/register/verify/{$token}")->assertOk();

        $this->assertNull(app(ChreeAccountRepository::class)->findByEmail('new@example.com'));
    }

    // 応答からアドレスの存在を推測させないため、登録済みでも画面は同じ
    public function test_hidesExistingEmailBehindSameResponse(): void {
        app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'new@example.com', '既存');

        $this->post('/register', $this->payload())->assertRedirect('/register/sent');

        Mail::assertSent(RegistrationExistsMail::class);
        Mail::assertNotSent(VerifyRegistrationMail::class);
        $this->assertSame(0, PendingRegistrationModel::query()->count());
    }

    public function test_rejectsShortPassword(): void {
        $this->post('/register', array_merge($this->payload(), ['password' => 'short']))
            ->assertSessionHasErrors('password');
    }

    public function test_dashboardRequiresLogin(): void {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_dashboardShowsAccountAfterVerification(): void {
        $token = $this->requestRegistration();
        $this->get("/register/verify/{$token}");

        $this->get('/')->assertOk();
    }
}
