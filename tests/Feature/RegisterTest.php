<?php
namespace Tests\Feature;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// アカウント登録
class RegisterTest extends TestCase {
    use RefreshDatabase;

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

    public function test_showsRegisterPage(): void {
        $this->get('/register')->assertOk();
    }

    public function test_createsAccountAndLogsIn(): void {
        $response = $this->post('/register', $this->payload());

        $response->assertRedirect('/');

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);
        $this->assertSame(AccountOrigin::USER, $account->origin);
        $this->assertSame($account->id, session('chreeid.account_id'));
    }

    public function test_setsPasswordCredential(): void {
        $this->post('/register', $this->payload());

        $account = app(ChreeAccountRepository::class)->findByEmail('new@example.com');
        $this->assertNotNull($account);

        $this->assertSame(1, CredentialModel::query()
            ->where('chree_account_id', $account->id)
            ->where('type', CredentialType::PASSWORD)
            ->count());
    }

    public function test_rejectsDuplicateEmail(): void {
        $this->post('/register', $this->payload());
        $this->post('/logout');

        $this->post('/register', $this->payload())->assertSessionHasErrors('email');
    }

    public function test_rejectsShortPassword(): void {
        $this->post('/register', array_merge($this->payload(), ['password' => 'short']))
            ->assertSessionHasErrors('password');
    }

    public function test_dashboardRequiresLogin(): void {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_dashboardShowsAccountAfterRegistration(): void {
        $this->post('/register', $this->payload());

        $this->get('/')->assertOk();
    }
}
