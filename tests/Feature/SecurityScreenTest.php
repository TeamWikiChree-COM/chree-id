<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\GenerateRecoveryCodes;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

// 認証方法の管理画面
class SecurityScreenTest extends TestCase {
    use RefreshDatabase;

    /**
     * アカウントを用意してログイン済みの状態にする。
     *
     * 登録画面はメール確認を挟むため、ここでは通さずに直接作る。
     *
     * @return string アカウントID (ULID)
     */
    private function register(): string {
        $account = app(ChreeAccountRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $id = session('chreeid.account_id');
        $this->assertIsString($id);

        return $id;
    }

    public function test_requiresLogin(): void {
        $this->get('/security')->assertRedirect('/login');
    }

    public function test_showsRegisteredCredentials(): void {
        $this->register();

        $this->get('/security')->assertOk();
    }

    public function test_startsTotpSetupWithoutEnabling(): void {
        $accountId = $this->register();

        $this->post('/security/totp/start')->assertRedirect('/security');

        // まだ有効化していない
        $this->assertSame(0, CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::TOTP)
            ->count());
    }

    public function test_confirmsTotpWithCorrectCode(): void {
        $accountId = $this->register();
        $this->post('/security/totp/start');

        $pending = session('security.pending_totp');
        $this->assertIsArray($pending);
        $secret = $pending['secret'];
        $this->assertIsString($secret);

        $this->post('/security/totp/confirm', ['code' => app(Totp::class)->at($secret, intdiv(time(), 30))])
            ->assertRedirect('/security');

        $row = CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::TOTP)
            ->firstOrFail();

        $this->assertSame($secret, Crypt::decryptString((string) $row->secret));
    }

    public function test_rejectsWrongTotpCode(): void {
        $this->register();
        $this->post('/security/totp/start');

        $this->post('/security/totp/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
    }

    public function test_rejectsConfirmWithoutStarting(): void {
        $this->register();

        $this->post('/security/totp/confirm', ['code' => '123456'])->assertSessionHasErrors('code');
    }

    public function test_generatesRecoveryCodes(): void {
        $accountId = $this->register();

        $this->post('/security/recovery-codes')->assertRedirect('/security');

        $this->assertSame(10, app(GenerateRecoveryCodes::class)->remaining($accountId));
        $this->assertIsArray(session('recoveryCodes'));
    }

    /**
     * 最後の1件を消せると本人がログインできなくなる
     */
    public function test_cannotRemoveLastCredential(): void {
        $this->register();

        $this->post('/security/credentials/remove', ['type' => 'password'])
            ->assertSessionHasErrors('type');
    }

    public function test_removesCredentialWhenAnotherRemains(): void {
        $accountId = $this->register();
        $this->post('/security/totp/start');
        $pending = session('security.pending_totp');
        $this->assertIsArray($pending);
        $secret = $pending['secret'];
        $this->assertIsString($secret);
        $this->post('/security/totp/confirm', ['code' => app(Totp::class)->at($secret, intdiv(time(), 30))]);

        $this->post('/security/credentials/remove', ['type' => 'totp'])->assertRedirect('/security');

        $this->assertSame(0, CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::TOTP)
            ->count());
    }

    public function test_passkeyOptionsRequireLogin(): void {
        $this->postJson('/security/passkey/options')->assertStatus(401);
    }

    public function test_returnsPasskeyOptions(): void {
        $this->register();

        $response = $this->postJson('/security/passkey/options');

        $response->assertOk();
        $response->assertJsonStructure(['challenge', 'rp' => ['id'], 'user' => ['id']]);
    }

    public function test_rejectsPasskeyRegistrationWithoutChallenge(): void {
        $this->register();

        $this->postJson('/security/passkey/register', ['credential' => '{}'])->assertStatus(400);
    }
}
