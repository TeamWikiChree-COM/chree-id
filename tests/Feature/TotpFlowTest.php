<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\EnableTotp;
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
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Tests\TestCase;

// TOTP を有効にしたあとの2要素認証
class TotpFlowTest extends TestCase {
    use RefreshDatabase;

    /**
     * @return ChreeAccount
     */
    private function makeAccount(): ChreeAccount {
        $account = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        return $account;
    }

    /**
     * @param string $accountId
     * @return string base32 の秘密鍵
     */
    private function enableTotp(string $accountId): string {
        $enable = app(EnableTotp::class);
        $secret = $enable->generateSecret();
        $enable->execute($accountId, $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));

        return $secret;
    }

    public function test_requiresSecondFactorAfterEnablingTotp(): void {
        $account = $this->makeAccount();
        $this->enableTotp($account->id);

        $factors = new VerifiedFactors();
        app(VerifyCredential::class)->execute($account->id, CredentialType::PASSWORD, ['password' => 'correct-horse'], $factors);

        $this->assertFalse(app(CompleteAuthentication::class)->execute($account->id, $factors));
    }

    public function test_completesWithPasswordAndTotp(): void {
        $account = $this->makeAccount();
        $secret = $this->enableTotp($account->id);

        $factors = new VerifiedFactors();
        $verify = app(VerifyCredential::class);
        $verify->execute($account->id, CredentialType::PASSWORD, ['password' => 'correct-horse'], $factors);
        $result = $verify->execute($account->id, CredentialType::TOTP, [
            'code' => app(Totp::class)->at($secret, intdiv(time(), 30)),
        ], $factors);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue(app(CompleteAuthentication::class)->execute($account->id, $factors));
    }

    public function test_rejectsWrongCode(): void {
        $account = $this->makeAccount();
        $this->enableTotp($account->id);

        $factors = new VerifiedFactors();
        $result = app(VerifyCredential::class)->execute($account->id, CredentialType::TOTP, ['code' => '000000'], $factors);

        $this->assertFalse($result->isSuccess());
    }

    public function test_rejectsEnablingWithWrongCode(): void {
        $account = $this->makeAccount();
        $enable = app(EnableTotp::class);

        $this->expectException(RuntimeException::class);
        $enable->execute($account->id, $enable->generateSecret(), '000000');
    }

    /**
     * TOTP はコード生成に元の値が要るので、暗号化して保存する (ハッシュ化しない)
     */
    public function test_storesSecretEncryptedNotHashed(): void {
        $account = $this->makeAccount();
        $secret = $this->enableTotp($account->id);

        $row = CredentialModel::query()
            ->where('chree_account_id', $account->id)
            ->where('type', CredentialType::TOTP)
            ->firstOrFail();

        $this->assertNotNull($row->secret);
        $this->assertNotSame($secret, $row->secret);
        $this->assertSame($secret, Crypt::decryptString($row->secret));
    }
}
