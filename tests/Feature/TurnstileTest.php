<?php
namespace Tests\Feature;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Support\Turnstile\TurnstileGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

// Turnstile。鍵が未設定なら検証そのものを行わない
class TurnstileTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
    }

    /**
     * @return void
     */
    private function configureTurnstile(): void {
        Config::set('chreeid.turnstile.site_key', 'site-key');
        Config::set('chreeid.turnstile.secret_key', 'secret-key');
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array {
        return [
            'email' => 'new@example.com',
            'password' => 'correct-horse',
            TurnstileGuard::FIELD => 'token-from-widget',
        ];
    }

    public function test_skipsVerificationWhenNotConfigured(): void {
        Http::fake();

        $this->post('/register', ['email' => 'new@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/register/sent');

        Http::assertNothingSent();
    }

    public function test_doesNotExposeSiteKeyWhenNotConfigured(): void {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('turnstileSiteKey', null));
    }

    public function test_exposesSiteKeyWhenConfigured(): void {
        $this->configureTurnstile();

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('turnstileSiteKey', 'site-key'));
    }

    public function test_acceptsRegistrationWhenTokenIsValid(): void {
        $this->configureTurnstile();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post('/register', $this->payload())->assertRedirect('/register/sent');
    }

    public function test_rejectsRegistrationWhenTokenIsInvalid(): void {
        $this->configureTurnstile();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->post('/register', $this->payload())->assertSessionHasErrors(TurnstileGuard::FIELD);

        $this->assertNull(app(AuthIdentityRepository::class)->findByEmail('new@example.com'));
        Mail::assertNothingSent();
    }

    public function test_rejectsRegistrationWhenTokenIsMissing(): void {
        $this->configureTurnstile();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post('/register', ['email' => 'new@example.com', 'password' => 'correct-horse'])
            ->assertSessionHasErrors(TurnstileGuard::FIELD);

        // 空トークンは Cloudflare に問い合わせるまでもなく落とす
        Http::assertNothingSent();
    }

    // Cloudflare に届かないのは利用者の落ち度ではないので、認証失敗とは区別する
    public function test_reportsOutageAsTemporaryFailure(): void {
        $this->configureTurnstile();
        Http::fake(['challenges.cloudflare.com/*' => Http::response('', 500)]);

        $this->post('/register', $this->payload())->assertSessionHasErrors(TurnstileGuard::FIELD);

        Mail::assertNothingSent();
    }

    public function test_guardsLoginAsWell(): void {
        $this->configureTurnstile();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'correct-horse',
            TurnstileGuard::FIELD => 'token-from-widget',
        ])->assertSessionHasErrors(TurnstileGuard::FIELD);

        $this->assertNull(session('chreeid.account_id'));
    }
}
