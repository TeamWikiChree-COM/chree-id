<?php
namespace Tests\Feature;

use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Registry\Application\PruneTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// 管理画面からの掃除。日次のコマンドと同じ処理を呼ぶ
class AdminMaintenanceTest extends TestCase {
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.com';

    /**
     * 管理者としてログインした状態にする。
     *
     * @param string $email 名乗るアドレス
     * @return string アカウントID (ULID)
     */
    private function loginAs(string $email): string {
        Config::set('chreeid.admin_emails', [self::ADMIN_EMAIL]);

        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, $email, '管理者');
        $accounts->markEmailVerified($account->id);

        $this->withSession(['chreeid.account_id' => $account->id]);

        return $account->id;
    }

    /**
     * 使われないまま期限が切れたトークンを1本置く。
     *
     * @param string $accountId アカウントID (ULID)
     * @return void
     */
    private function addExpiredToken(string $accountId): void {
        OneTimeTokenModel::create([
            'auth_identity_id' => $accountId,
            'purpose' => 'verify_email',
            'token_hash' => hash('sha256', Str::random(32)),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function test_showsWhatWouldBeRemoved(): void {
        $accountId = $this->loginAs(self::ADMIN_EMAIL);
        $this->addExpiredToken($accountId);

        $this->get('/admin/maintenance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Maintenance/Index')
                ->where('pending.expiredTokens', 1)
                ->where('pending.total', 1));
    }

    public function test_removesThemWhenAsked(): void {
        $accountId = $this->loginAs(self::ADMIN_EMAIL);
        $this->addExpiredToken($accountId);

        $this->post('/admin/maintenance/prune')->assertRedirect('/admin/maintenance');

        $this->assertSame(0, OneTimeTokenModel::query()->count());
        $this->assertSame(1, session('prunedTokens'));
    }

    // 数えるのと消すのが同じ条件でないと、出した件数と結果が食い違う
    public function test_countsMatchWhatGetsRemoved(): void {
        $accountId = $this->loginAs(self::ADMIN_EMAIL);
        $this->addExpiredToken($accountId);
        $this->addExpiredToken($accountId);

        $prune = app(PruneTokens::class);
        $expected = $prune->pending()->total();

        $this->assertSame($expected, $prune->execute()->total());
    }

    // まだ使えるトークンを巻き添えにしない
    public function test_keepsTokensThatAreStillValid(): void {
        $accountId = $this->loginAs(self::ADMIN_EMAIL);

        OneTimeTokenModel::create([
            'auth_identity_id' => $accountId,
            'purpose' => 'verify_email',
            'token_hash' => hash('sha256', Str::random(32)),
            'expires_at' => now()->addHour(),
        ]);

        $this->post('/admin/maintenance/prune');

        $this->assertSame(1, OneTimeTokenModel::query()->count());
    }

    // 管理者以外に運営の操作をさせない。あることも伏せる
    public function test_hidesThePageFromEveryoneElse(): void {
        $accountId = $this->loginAs('someone@example.com');
        $this->addExpiredToken($accountId);

        $this->get('/admin/maintenance')->assertNotFound();
        $this->post('/admin/maintenance/prune')->assertNotFound();

        $this->assertSame(1, OneTimeTokenModel::query()->count());
    }

    public function test_sendsVisitorsToLogin(): void {
        $this->get('/admin/maintenance')->assertRedirect('/login');
    }
}
