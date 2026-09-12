<?php
namespace Tests\Feature;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Audit\Infrastructure\AuditEventModel;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// 監査ログ。本人が「自分の身に何が起きたか」を追えること
class AuditLogTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
        RateLimiter::clear('challenge');
    }

    /**
     * @param string $email 連絡先
     * @return string アカウントID (ULID)
     */
    private function account(string $email = 'user@example.com'): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, $email, 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        return $account->id;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<AuditEventModel> 古い順
     */
    private function events(string $accountId): array {
        return array_values(
            AuditEventModel::query()
                ->where('auth_identity_id', $accountId)
                ->orderBy('id')
                ->get()
                ->all(),
        );
    }

    public function test_recordsSuccessfulLogin(): void {
        $accountId = $this->account();

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/');

        $events = $this->events($accountId);
        $this->assertCount(1, $events);
        $this->assertSame(AuditAction::LOGIN_SUCCEEDED, $events[0]->action);
        $this->assertSame('password', $events[0]->context['method'] ?? null);
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
        $this->assertSame(AuditAction::LOGIN_FAILED, $events[0]->action);
        $this->assertFalse($events[0]->succeeded);
    }

    /**
     * 知らないアドレスでは記録しない (紐付ける先が無い)
     */
    public function test_ignoresUnknownEmail(): void {
        $this->account();

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

        $this->assertSame(0, AuditEventModel::query()->count());
    }

    public function test_recordsSecondFactor(): void {
        $accountId = $this->account();
        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($accountId, $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/login/challenge');

        // パスワードだけでは成立していないので、この時点では記録しない
        $this->assertSame(0, AuditEventModel::query()->count());

        $this->post('/login/challenge', ['code' => app(Totp::class)->at($secret, intdiv(time(), 30))])
            ->assertRedirect('/');

        $events = $this->events($accountId);
        $this->assertCount(1, $events);
        $this->assertSame('totp', $events[0]->context['method'] ?? null);
    }

    public function test_recordsPasswordChange(): void {
        $accountId = $this->account();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->post('/settings/password', [
            'current_password' => 'correct-horse',
            'password' => 'battery-staple',
            'password_confirmation' => 'battery-staple',
        ])->assertRedirect('/settings/security');

        $actions = array_map(fn (AuditEventModel $e): AuditAction => $e->action, $this->events($accountId));
        $this->assertContains(AuditAction::PASSWORD_CHANGED, $actions);
    }

    /**
     * ログアウトしても消えない。消えると身に覚えのない操作に気付けない
     */
    public function test_survivesLogout(): void {
        $accountId = $this->account();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->post('/logout');

        $this->assertCount(1, $this->events($accountId));
    }

    public function test_requiresLoginForActivity(): void {
        $this->get('/settings/activity')->assertRedirect('/login');
    }

    /**
     * 他人の記録が混ざること自体が漏洩になる
     */
    public function test_showsOnlyOwnEvents(): void {
        $accountId = $this->account();
        $other = $this->account('other@example.com');
        app(AuditLog::class)->record(AuditAction::PASSWORD_CHANGED, $other);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->get('/settings/activity')->assertOk();

        $listed = app(AuditLog::class)->listFor($accountId);
        $this->assertCount(1, $listed);
    }

    public function test_adminAuditRequiresAdmin(): void {
        $this->account();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->get('/admin/audit')->assertNotFound();
    }

    public function test_prunesOldEvents(): void {
        $accountId = $this->account();
        app(AuditLog::class)->record(AuditAction::PASSWORD_CHANGED, $accountId);

        AuditEventModel::query()->update(['created_at' => now()->subDays(AuditLog::KEEP_DAYS + 1)]);
        app(AuditLog::class)->record(AuditAction::PASSWORD_CHANGED, $accountId);

        $this->assertSame(1, app(AuditLog::class)->prune());
        $this->assertCount(1, $this->events($accountId));
    }
}
