<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\Verifiers\PasswordVerifier;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 設定画面からのパスワード変更
class ChangePasswordTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param bool $withPassword パスワードを設定済みにするか
     * @return string アカウントID (ULID)
     */
    private function login(bool $withPassword = true): string {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');

        if ($withPassword) {
            app(SetPassword::class)->execute($account->id, 'correct-horse');
            $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

            return $account->id;
        }

        // パスワードを持たないアカウントでも、ログイン中であること自体が裏付けになる
        $this->withSession(['chreeid.account_id' => $account->id]);

        return $account->id;
    }

    public function test_requiresLogin(): void {
        $this->post('/settings/password', ['password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertRedirect('/login');
    }

    public function test_changesPassword(): void {
        $accountId = $this->login();

        $this->post('/settings/password', [
            'current_password' => 'correct-horse',
            'password' => 'battery-staple',
            'password_confirmation' => 'battery-staple',
        ])->assertRedirect('/settings/security');

        $this->assertTrue(
            app(PasswordVerifier::class)->verifyPassword($accountId, 'battery-staple')->isSuccess(),
        );
    }

    /**
     * 現在のパスワードを知らない人に変えさせない (端末を離れた隙に乗っ取られる)
     */
    public function test_rejectsWrongCurrentPassword(): void {
        $accountId = $this->login();

        $this->post('/settings/password', [
            'current_password' => 'wrong',
            'password' => 'battery-staple',
            'password_confirmation' => 'battery-staple',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(
            app(PasswordVerifier::class)->verifyPassword($accountId, 'correct-horse')->isSuccess(),
        );
    }

    public function test_rejectsMismatchedConfirmation(): void {
        $this->login();

        $this->post('/settings/password', [
            'current_password' => 'correct-horse',
            'password' => 'battery-staple',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    }

    public function test_rejectsShortPassword(): void {
        $this->login();

        $this->post('/settings/password', [
            'current_password' => 'correct-horse',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    /**
     * まだ持っていないアカウントは、現在のパスワードを聞かずに新規設定する
     */
    public function test_setsPasswordWhenNoneExists(): void {
        $accountId = $this->login(withPassword: false);

        $this->post('/settings/password', [
            'password' => 'battery-staple',
            'password_confirmation' => 'battery-staple',
        ])->assertRedirect('/settings/security');

        $this->assertSame(1, CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::PASSWORD)
            ->count());
    }
}
