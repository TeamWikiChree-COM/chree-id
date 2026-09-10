<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// 認証まわりのレート制限。Turnstile が未設定でも効く
class RateLimitTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('login');
        RateLimiter::clear('register');
    }

    public function test_throttlesRepeatedLoginFailures(): void {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $attempt = fn (): \Illuminate\Testing\TestResponse => $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $attempt()->assertStatus(302);
        }

        $attempt()->assertStatus(429);
    }

    // 正しいパスワードでも、上限に達していれば通さない
    public function test_throttleAppliesToCorrectPasswordToo(): void {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertStatus(429);
        $this->assertNull(session('chreeid.account_id'));
    }

    public function test_throttlesRepeatedRegistrations(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', ['email' => 'new@example.com', 'password' => 'correct-horse'])
                ->assertStatus(302);
        }

        $this->post('/register', ['email' => 'new@example.com', 'password' => 'correct-horse'])
            ->assertStatus(429);
    }

    // アドレスを変えても、同じ IP からの連投はまとめて止める
    public function test_throttlesRegistrationsAcrossAddresses(): void {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register', ['email' => "user{$i}@example.com", 'password' => 'correct-horse'])
                ->assertStatus(302);
        }

        $this->post('/register', ['email' => 'another@example.com', 'password' => 'correct-horse'])
            ->assertStatus(429);
    }
}
