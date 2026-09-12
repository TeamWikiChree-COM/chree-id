<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Device\Application\TrustedDevices;
use App\Modules\Device\Infrastructure\TrustedDeviceModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// 2段階目を通した端末を覚えて、次回は省略する
class TrustedDeviceTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('challenge');
        RateLimiter::clear('login');
    }

    /**
     * パスワードと TOTP を持つアカウントを作る。
     *
     * @return array{id: string, secret: string}
     */
    private function account(): array {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($account->id, $secret, $this->codeFor($secret));

        return ['id' => $account->id, 'secret' => $secret];
    }

    /**
     * @param string $secret TOTP の秘密鍵
     * @return string いまの時間枠のコード
     */
    private function codeFor(string $secret): string {
        return app(Totp::class)->at($secret, intdiv(time(), 30));
    }

    /**
     * 2段階目まで通し、この端末を記憶させる。
     *
     * @param array{id: string, secret: string} $account
     * @return string 端末に配られた平文トークン
     */
    private function trustThisDevice(array $account): string {
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $response = $this->post('/login/challenge', [
            'code' => $this->codeFor($account['secret']),
            'trustDevice' => true,
        ]);
        $response->assertRedirect('/');

        $cookie = $response->getCookie(TrustedDevices::COOKIE, false);
        $this->assertNotNull($cookie);
        $token = $cookie->getValue();
        $this->assertIsString($token);

        return $token;
    }

    public function test_doesNotTrustWhenNotAsked(): void {
        $account = $this->account();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->post('/login/challenge', ['code' => $this->codeFor($account['secret'])])
            ->assertRedirect('/');

        $this->assertSame(0, TrustedDeviceModel::query()->count());
    }

    public function test_skipsSecondFactorOnTrustedDevice(): void {
        $account = $this->account();
        $token = $this->trustThisDevice($account);

        $this->post('/logout');

        $this->withUnencryptedCookie(TrustedDevices::COOKIE, $token)
            ->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/');

        $this->assertSame($account['id'], session('chreeid.account_id'));
    }

    /**
     * 別の端末 (トークンを持たない) は、いつも通り2段階目を求められる
     */
    public function test_stillChallengesOtherDevices(): void {
        $account = $this->account();
        $this->trustThisDevice($account);

        $this->post('/logout');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/login/challenge');
    }

    public function test_expiredTrustDoesNotSkip(): void {
        $account = $this->account();
        $token = $this->trustThisDevice($account);

        TrustedDeviceModel::query()->update(['expires_at' => now()->subDay()]);
        $this->post('/logout');

        $this->withUnencryptedCookie(TrustedDevices::COOKIE, $token)
            ->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/login/challenge');
    }

    public function test_revokedTrustDoesNotSkip(): void {
        $account = $this->account();
        $token = $this->trustThisDevice($account);

        $device = TrustedDeviceModel::query()->firstOrFail();
        $this->post('/settings/devices/trusted/revoke', ['id' => $device->id])
            ->assertRedirect('/settings/devices');

        $this->post('/logout');

        $this->withUnencryptedCookie(TrustedDevices::COOKIE, $token)
            ->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/login/challenge');
    }

    public function test_listsTrustedDevices(): void {
        $account = $this->account();
        $this->trustThisDevice($account);

        $this->get('/settings/devices')->assertOk();

        $this->assertSame(1, TrustedDeviceModel::query()->count());
    }
}
