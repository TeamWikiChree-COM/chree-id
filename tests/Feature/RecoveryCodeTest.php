<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\GenerateRecoveryCodes;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccount;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 復旧コード。TOTP の端末を失くしたときの2要素目の代わり
class RecoveryCodeTest extends TestCase {
    use RefreshDatabase;

    /**
     * TOTP まで有効にしたアカウントを作る
     *
     * @return ChreeAccount
     */
    private function makeAccountWithTotp(): ChreeAccount {
        $account = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $enable = app(EnableTotp::class);
        $secret = $enable->generateSecret();
        $enable->execute($account->id, $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));

        return $account;
    }

    public function test_generatesCodes(): void {
        $account = $this->makeAccountWithTotp();

        $codes = app(GenerateRecoveryCodes::class)->execute($account->id);

        $this->assertCount(10, $codes);
        $this->assertSame(10, app(GenerateRecoveryCodes::class)->remaining($account->id));
    }

    /**
     * 平文は返すときだけ。DB にはハッシュしか残さない
     */
    public function test_storesOnlyHashedCodes(): void {
        $account = $this->makeAccountWithTotp();
        $codes = app(GenerateRecoveryCodes::class)->execute($account->id);

        $stored = CredentialModel::query()
            ->where('chree_account_id', $account->id)
            ->where('type', CredentialType::RECOVERY_CODE)
            ->pluck('secret')
            ->all();

        $this->assertNotContains($codes[0], $stored);
        $this->assertContains(hash('sha256', $codes[0]), $stored);
    }

    public function test_completesAuthenticationWithPasswordAndRecoveryCode(): void {
        $account = $this->makeAccountWithTotp();
        $codes = app(GenerateRecoveryCodes::class)->execute($account->id);

        $factors = new VerifiedFactors();
        $verify = app(VerifyCredential::class);
        $verify->execute($account->id, CredentialType::PASSWORD, ['password' => 'correct-horse'], $factors);
        $result = $verify->execute($account->id, CredentialType::RECOVERY_CODE, ['code' => $codes[0]], $factors);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue(app(CompleteAuthentication::class)->execute($account->id, $factors));
    }

    /**
     * 使い捨て。残しておくと同じコードで何度も入れてしまう
     */
    public function test_codeCannotBeUsedTwice(): void {
        $account = $this->makeAccountWithTotp();
        $codes = app(GenerateRecoveryCodes::class)->execute($account->id);

        $verify = app(VerifyCredential::class);
        $first = $verify->execute($account->id, CredentialType::RECOVERY_CODE, ['code' => $codes[0]], new VerifiedFactors());
        $second = $verify->execute($account->id, CredentialType::RECOVERY_CODE, ['code' => $codes[0]], new VerifiedFactors());

        $this->assertTrue($first->isSuccess());
        $this->assertFalse($second->isSuccess());
        $this->assertSame(9, app(GenerateRecoveryCodes::class)->remaining($account->id));
    }

    public function test_rejectsUnknownCode(): void {
        $account = $this->makeAccountWithTotp();
        app(GenerateRecoveryCodes::class)->execute($account->id);

        $result = app(VerifyCredential::class)
            ->execute($account->id, CredentialType::RECOVERY_CODE, ['code' => 'not-a-real-code'], new VerifiedFactors());

        $this->assertFalse($result->isSuccess());
    }

    /**
     * 作り直したら前のコードは使えなくする
     */
    public function test_regeneratingInvalidatesOldCodes(): void {
        $account = $this->makeAccountWithTotp();
        $generate = app(GenerateRecoveryCodes::class);
        $old = $generate->execute($account->id);
        $generate->execute($account->id);

        $result = app(VerifyCredential::class)
            ->execute($account->id, CredentialType::RECOVERY_CODE, ['code' => $old[0]], new VerifiedFactors());

        $this->assertFalse($result->isSuccess());
        $this->assertSame(10, $generate->remaining($account->id));
    }

    /**
     * 単独では認証を完了させない。あくまで2要素目の代わり
     */
    public function test_isNotSufficientAlone(): void {
        $account = $this->makeAccountWithTotp();
        $codes = app(GenerateRecoveryCodes::class)->execute($account->id);

        $factors = new VerifiedFactors();
        app(VerifyCredential::class)->execute($account->id, CredentialType::RECOVERY_CODE, ['code' => $codes[0]], $factors);

        $this->assertFalse(app(CompleteAuthentication::class)->execute($account->id, $factors));
    }
}
