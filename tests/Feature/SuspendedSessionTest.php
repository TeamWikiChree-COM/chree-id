<?php
namespace Tests\Feature;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 停止・退会したアカウントの既存セッションが切れること、および防御ヘッダー
class SuspendedSessionTest extends TestCase {
    use RefreshDatabase;

    /**
     * @return string ログイン状態にしたアカウントID
     */
    private function loggedIn(): string {
        $id = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', '利用者')->id;
        $this->withSession(['chreeid.account_id' => $id]);

        return $id;
    }

    public function test_activeAccountStaysLoggedIn(): void {
        $this->loggedIn();

        $this->get('/settings')->assertOk();
        $this->assertNotNull(session('chreeid.account_id'));
    }

    public function test_suspendedAccountIsLoggedOut(): void {
        $id = $this->loggedIn();
        app(AuthIdentityRepository::class)->suspend($id);

        $this->get('/settings');

        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_withdrawnAccountIsLoggedOut(): void {
        $id = $this->loggedIn();
        app(AuthIdentityRepository::class)->softDelete($id);

        $this->get('/settings');

        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_responsesCarrySecurityHeaders(): void {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
