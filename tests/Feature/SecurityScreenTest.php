<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\GenerateRecoveryCodes;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
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
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $id = session('chreeid.account_id');
        $this->assertIsString($id);

        return $id;
    }

    public function test_requiresLogin(): void {
        $this->get('/settings/security')->assertRedirect('/login');
    }

    public function test_showsRegisteredCredentials(): void {
        $this->register();

        $this->get('/settings/security')->assertOk();
    }

    public function test_startsTotpSetupWithoutEnabling(): void {
        $accountId = $this->register();

        $this->post('/security/totp/start')->assertRedirect('/settings/security');

        // まだ有効化していない
        $this->assertSame(0, CredentialModel::query()
            ->where('auth_identity_id', $accountId)
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
            ->assertRedirect('/settings/security');

        $row = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
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

        $this->post('/security/recovery-codes')->assertRedirect('/settings/security');

        $this->assertSame(10, app(GenerateRecoveryCodes::class)->remaining($accountId));
        $this->assertIsArray(session('recoveryCodes'));
    }

    /**
     * 最後の1件を消せると本人がログインできなくなる
     */
    public function test_cannotRemoveLastCredential(): void {
        $accountId = $this->register();

        $this->post('/security/credentials/remove', ['id' => $this->credentialId($accountId, CredentialType::PASSWORD)])
            ->assertSessionHasErrors('credential');
    }

    public function test_removesCredentialWhenAnotherRemains(): void {
        $accountId = $this->register();
        $this->post('/security/totp/start');
        $pending = session('security.pending_totp');
        $this->assertIsArray($pending);
        $secret = $pending['secret'];
        $this->assertIsString($secret);
        $this->post('/security/totp/confirm', ['code' => app(Totp::class)->at($secret, intdiv(time(), 30))]);

        $this->post('/security/credentials/remove', ['id' => $this->credentialId($accountId, CredentialType::TOTP)])
            ->assertRedirect('/settings/security');

        $this->assertSame(0, CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::TOTP)
            ->count());
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param CredentialType $type 探す認証方式
     * @return string 認証手段のID (ULID)
     */
    private function credentialId(string $accountId, CredentialType $type): string {
        $row = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', $type)
            ->firstOrFail();

        return $row->id;
    }

    /**
     * 同じ端末名が並んだときに見分けられないと困る
     */
    public function test_renamesPasskey(): void {
        $accountId = $this->register();
        $row = CredentialModel::query()->create([
            'auth_identity_id' => $accountId,
            'type' => CredentialType::PASSKEY,
            'identifier' => 'cred-1',
            'data' => ['label' => 'Chrome (Windows)', 'sign_count' => 3],
        ]);

        $this->post('/security/credentials/rename', ['id' => $row->id, 'label' => '仕事用ノート'])
            ->assertRedirect('/settings/security');

        $data = CredentialModel::query()->findOrFail($row->id)->data;
        $this->assertSame('仕事用ノート', $data['label']);
        // 検証に使う値を巻き添えにしない
        $this->assertSame(3, $data['sign_count']);
    }

    /**
     * 名前を持たない認証手段には付けさせない
     */
    public function test_cannotRenamePassword(): void {
        $accountId = $this->register();

        $this->post('/security/credentials/rename', [
            'id' => $this->credentialId($accountId, CredentialType::PASSWORD),
            'label' => 'なにか',
        ])->assertSessionHasErrors('credential');
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

    // 控えずに端末を失うと詰む。2FA を有効にした時点で必ず発行し、その場で見せる
    public function test_handsBackRecoveryCodesWhenTotpIsEnabled(): void {
        $accountId = $this->register();
        $this->post('/security/totp/start');

        $pending = session('security.pending_totp');
        $this->assertIsArray($pending);
        $secret = $pending['secret'];
        $this->assertIsString($secret);

        $this->post('/security/totp/confirm', ['code' => app(Totp::class)->at($secret, intdiv(time(), 30))])
            ->assertRedirect('/settings/security')
            ->assertSessionHas('recoveryCodes');

        $this->assertSame(10, app(GenerateRecoveryCodes::class)->remaining($accountId));
    }

    // コードが合わなければ、2FA も復旧コードもできていない
    public function test_leavesNoRecoveryCodesWhenTheTotpCodeIsWrong(): void {
        $accountId = $this->register();
        $this->post('/security/totp/start');

        $this->post('/security/totp/confirm', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertSame(0, app(GenerateRecoveryCodes::class)->remaining($accountId));
    }
}
