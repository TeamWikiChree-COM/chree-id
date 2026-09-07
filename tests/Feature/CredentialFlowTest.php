<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\EnableMagicLink;
use App\Modules\Credential\Application\IssueMagicLink;
use App\Modules\Credential\Application\RemoveCredential;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccount;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

// 認証手段の登録から検証までの通しテスト
class CredentialFlowTest extends TestCase {
    use RefreshDatabase;

    /**
     * @return ChreeAccount
     */
    private function makeAccount(): ChreeAccount {
        return app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'test@example.com', 'テスト');
    }

    public function test_パスワードを設定すると認証できる(): void {
        $account = $this->makeAccount();
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $factors = new VerifiedFactors();
        $result = app(VerifyCredential::class)->execute($account->id, CredentialType::PASSWORD, ['password' => 'correct-horse'], $factors);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue(app(CompleteAuthentication::class)->execute($account->id, $factors));
    }

    public function test_誤ったパスワードでは認証できない(): void {
        $account = $this->makeAccount();
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $factors = new VerifiedFactors();
        $result = app(VerifyCredential::class)->execute($account->id, CredentialType::PASSWORD, ['password' => 'wrong'], $factors);

        $this->assertFalse($result->isSuccess());
        $this->assertFalse(app(CompleteAuthentication::class)->execute($account->id, $factors));
    }

    public function test_短すぎるパスワードは弾かれる(): void {
        $account = $this->makeAccount();

        $this->expectException(InvalidArgumentException::class);
        app(SetPassword::class)->execute($account->id, 'short');
    }

    public function test_マジックリンクは一度しか使えない(): void {
        $account = $this->makeAccount();
        app(EnableMagicLink::class)->execute($account->id);
        $token = app(IssueMagicLink::class)->execute($account->id);

        $first = app(VerifyCredential::class)->execute($account->id, CredentialType::MAGIC_LINK, ['token' => $token], new VerifiedFactors());
        $second = app(VerifyCredential::class)->execute($account->id, CredentialType::MAGIC_LINK, ['token' => $token], new VerifiedFactors());

        $this->assertTrue($first->isSuccess());
        $this->assertFalse($second->isSuccess());
    }

    public function test_有効化していないアカウントはトークンを発行できない(): void {
        $account = $this->makeAccount();

        $this->expectException(RuntimeException::class);
        app(IssueMagicLink::class)->execute($account->id);
    }

    public function test_最後の認証手段は削除できない(): void {
        $account = $this->makeAccount();
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->expectException(RuntimeException::class);
        app(RemoveCredential::class)->execute($account->id, CredentialType::PASSWORD);
    }

    public function test_他に手段があれば削除できる(): void {
        $account = $this->makeAccount();
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        app(EnableMagicLink::class)->execute($account->id);

        app(RemoveCredential::class)->execute($account->id, CredentialType::MAGIC_LINK);

        $factors = new VerifiedFactors();
        $result = app(VerifyCredential::class)->execute($account->id, CredentialType::MAGIC_LINK, ['token' => 'whatever'], $factors);

        $this->assertFalse($result->isSuccess());
    }
}
