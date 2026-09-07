<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccount;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

// パスワードによるログイン
class LoginTest extends TestCase {
    use RefreshDatabase;

    /**
     * @return ChreeAccount
     */
    private function makeAccountWithPassword(): ChreeAccount {
        $account = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        return $account;
    }

    /**
     * 直前のリクエストが残したエラー文言
     *
     * @return list<string>
     */
    private function emailErrors(): array {
        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);

        $messages = [];
        foreach ($errors->get('email') as $message) {
            $this->assertIsString($message);
            $messages[] = $message;
        }

        return $messages;
    }

    public function test_showsLoginPage(): void {
        $this->get('/login')->assertOk();
    }

    public function test_logsInWithCorrectPassword(): void {
        $account = $this->makeAccountWithPassword();

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'correct-horse',
        ]);

        $response->assertRedirect('/');
        $this->assertSame($account->id, session('chreeid.account_id'));
    }

    public function test_rejectsWrongPassword(): void {
        $this->makeAccountWithPassword();

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertNull(session('chreeid.account_id'));
    }

    /**
     * 未登録のメールでも、間違ったパスワードと同じ文言を返す。
     * 出し分けるとアカウントの存在を総当たりで調べられる。
     */
    public function test_doesNotRevealWhetherAccountExists(): void {
        $this->makeAccountWithPassword();

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever'])->assertSessionHasErrors('email');
        $unknown = $this->emailErrors();

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $wrong = $this->emailErrors();

        $this->assertSame($unknown, $wrong);
    }

    public function test_logsOut(): void {
        $this->makeAccountWithPassword();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->post('/logout')->assertRedirect('/login');

        $this->assertNull(session('chreeid.account_id'));
    }
}
