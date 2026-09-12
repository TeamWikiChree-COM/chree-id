<?php
namespace Tests\Feature;

use App\Modules\Audit\Application\LoginHistory;
use App\Modules\Audit\Infrastructure\LoginEventModel;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// 本人が見るログイン履歴
class LoginHistoryTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
        RateLimiter::clear('challenge');
    }

    /**
     * @return string アカウントID (ULID)
     */
    private function account(): string {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        return $account->id;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<LoginEventModel> 古い順
     */
    private function events(string $accountId): array {
        return LoginEventModel::query()
            ->where('auth_identity_id', $accountId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function test_recordsSuccessfulLogin(): void {
        $accountId = $this->account();

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/');

        $events = $this->events($accountId);
        $this->assertCount(1, $events);
        $this->assertSame('password', $events[0]->method);
        $this->assertTrue($events[0]->succeeded);
    }

    /**
     * 誰かがパスワードを試している事実は、成功と同じくらい本人が知りたい
     */
    public function test_recordsFailedPassword(): void {
        $accountId = $this->account();

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $events = $this->events($accountId);
        $this->assertCount(1, $events);
        $this->assertFalse($events[0]->succeeded);
    }

    /**
     * 知らないアドレスでは記録しない (紐付ける先が無い)
     */
    public function test_ignoresUnknownEmail(): void {
        $this->account();

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

        $this->assertSame(0, LoginEventModel::query()->count());
    }

    public function test_recordsSecondFactor(): void {
        $accountId = $this->account();
        $secret = app(EnableTotp::class)->generateSecret();
        $code = app(Totp::class)->at($secret, intdiv(time(), 30));
        app(EnableTotp::class)->execute($accountId, $secret, $code);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/login/challenge');

        // パスワードだけでは成立していないので、この時点では記録しない
        $this->assertSame(0, LoginEventModel::query()->count());

        $this->post('/login/challenge', ['code' => app(Totp::class)->at($secret, intdiv(time(), 30))])
            ->assertRedirect('/');

        $events = $this->events($accountId);
        $this->assertCount(1, $events);
        $this->assertSame('totp', $events[0]->method);
    }

    public function test_recordsWrongSecondFactor(): void {
        $accountId = $this->account();
        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($accountId, $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);
        $this->post('/login/challenge', ['code' => '000000'])->assertSessionHasErrors('code');

        $events = $this->events($accountId);
        $this->assertCount(1, $events);
        $this->assertFalse($events[0]->succeeded);
    }

    /**
     * ログアウトしても消えない。消えると身に覚えのないログインに気付けない
     */
    public function test_survivesLogout(): void {
        $accountId = $this->account();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->post('/logout');

        $this->assertCount(1, $this->events($accountId));
    }

    public function test_showsHistoryOnDevicesPage(): void {
        $this->account();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->get('/settings/devices')->assertOk();
    }

    public function test_prunesOldEvents(): void {
        $accountId = $this->account();
        app(LoginHistory::class)->record($accountId, 'password');

        LoginEventModel::query()->update(['created_at' => now()->subDays(LoginHistory::KEEP_DAYS + 1)]);
        app(LoginHistory::class)->record($accountId, 'password');

        $this->assertSame(1, app(LoginHistory::class)->prune());
        $this->assertCount(1, $this->events($accountId));
    }
}
