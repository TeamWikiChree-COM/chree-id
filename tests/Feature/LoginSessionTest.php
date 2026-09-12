<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Device\Application\LoginSessions;
use App\Modules\Device\Infrastructure\LoginSessionModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// ログイン中の端末の一覧と失効
//
// **いま使っている端末を切る経路はここでは通せない。** テストクライアントは
// セッションのクッキーを運ばず、リクエストごとに別のIDになる。ブラウザなら
// 同じIDが続くので、その分岐は LoginSessions::forget の振る舞いとして確かめる。
class LoginSessionTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');

        // 一覧は sessions テーブルに実体がある行だけを出す。
        // 既定の array ドライバでは実体が残らず、何も出せない
        config(['session.driver' => 'database']);
    }

    /**
     * @return string アカウントID (ULID)
     */
    private function login(): string {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        return $account->id;
    }

    public function test_requiresLogin(): void {
        $this->get('/settings/devices')->assertRedirect('/login');
    }

    /**
     * いま生きている端末の数。
     *
     * 控えだけ残った行は数えない。セッションIDは再生成されるので、
     * login_sessions には実体を失った行が残ることがある。
     *
     * @param string $accountId アカウントID (ULID)
     * @return int
     */
    private function aliveCount(string $accountId): int {
        return LoginSessionModel::query()
            ->where('auth_identity_id', $accountId)
            ->whereIn('id', DB::table('sessions')->pluck('id')->all())
            ->count();
    }

    public function test_recordsSessionOfLoggedInUser(): void {
        $accountId = $this->login();

        $this->get('/settings/devices')->assertOk();

        $this->assertSame(1, $this->aliveCount($accountId));
    }

    /**
     * 控えだけ消しても実体は生きている。両方消えることを確かめる
     */
    public function test_forgetRemovesBothRecordAndSession(): void {
        $accountId = $this->login();

        DB::table('sessions')->insert([
            'id' => 'some-session',
            'payload' => '',
            'last_activity' => time(),
        ]);
        LoginSessionModel::query()->create([
            'id' => 'some-session',
            'auth_identity_id' => $accountId,
            'last_active_at' => now(),
        ]);

        app(LoginSessions::class)->forget('some-session');

        $this->assertSame(0, LoginSessionModel::query()->where('id', 'some-session')->count());
        $this->assertSame(0, DB::table('sessions')->where('id', 'some-session')->count());
    }

    public function test_revokesOtherSessions(): void {
        $accountId = $this->login();
        $this->get('/settings/devices');

        // 別の端末から入ったことにする。実体と控えの両方を置く
        DB::table('sessions')->insert([
            'id' => 'other-session',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Mozilla/5.0 (Macintosh) Safari/605',
            'payload' => '',
            'last_activity' => time(),
        ]);
        LoginSessionModel::query()->create([
            'id' => 'other-session',
            'auth_identity_id' => $accountId,
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Mozilla/5.0 (Macintosh) Safari/605',
            'last_active_at' => now(),
        ]);

        $this->post('/settings/devices/sessions/revoke-others')->assertRedirect('/settings/devices');

        $this->assertSame(0, LoginSessionModel::query()->where('id', 'other-session')->count());
        $this->assertSame(0, DB::table('sessions')->where('id', 'other-session')->count());
        $this->assertSame($accountId, session('chreeid.account_id'));
    }

    /**
     * 他人のセッションを切れてはいけない
     */
    public function test_cannotRevokeSessionOfAnotherAccount(): void {
        $this->login();

        $other = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'other@example.com', '別人');
        DB::table('sessions')->insert([
            'id' => 'victim-session',
            'payload' => '',
            'last_activity' => time(),
        ]);
        LoginSessionModel::query()->create([
            'id' => 'victim-session',
            'auth_identity_id' => $other->id,
            'last_active_at' => now(),
        ]);

        $this->post('/settings/devices/sessions/revoke', ['id' => 'victim-session'])
            ->assertRedirect('/settings/devices');

        $this->assertSame(1, DB::table('sessions')->where('id', 'victim-session')->count());
    }

    /**
     * 実体を失った控えは掃除で落とす
     */
    public function test_prunesRecordsWithoutSession(): void {
        $accountId = $this->login();

        LoginSessionModel::query()->create([
            'id' => 'dead-session',
            'auth_identity_id' => $accountId,
            'last_active_at' => now(),
        ]);

        app(LoginSessions::class)->prune();

        $this->assertSame(0, LoginSessionModel::query()->where('id', 'dead-session')->count());
    }
}
