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
use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

// 認証手段の登録から検証までの通しテスト
class CredentialFlowTest extends TestCase {
    use RefreshDatabase;

    /**
     * @return AuthIdentity
     */
    private function makeAccount(): AuthIdentity {
        return app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'test@example.com', 'テスト');
    }

    public function test_verifiesWithConfiguredPassword(): void {
        $account = $this->makeAccount();
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $factors = new VerifiedFactors();
        $result = app(VerifyCredential::class)->execute($account->id, CredentialType::PASSWORD, ['password' => 'correct-horse'], $factors);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue(app(CompleteAuthentication::class)->execute($account->id, $factors));
    }

    public function test_rejectsWrongPassword(): void {
        $account = $this->makeAccount();
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $factors = new VerifiedFactors();
        $result = app(VerifyCredential::class)->execute($account->id, CredentialType::PASSWORD, ['password' => 'wrong'], $factors);

        $this->assertFalse($result->isSuccess());
        $this->assertFalse(app(CompleteAuthentication::class)->execute($account->id, $factors));
    }

    public function test_rejectsTooShortPassword(): void {
        $account = $this->makeAccount();

        $this->expectException(InvalidArgumentException::class);
        app(SetPassword::class)->execute($account->id, 'short');
    }

    public function test_magicLinkTokenIsSingleUse(): void {
        $account = $this->makeAccount();
        app(EnableMagicLink::class)->execute($account->id);
        $token = app(IssueMagicLink::class)->execute($account->id);

        $first = app(VerifyCredential::class)->execute($account->id, CredentialType::MAGIC_LINK, ['token' => $token], new VerifiedFactors());
        $second = app(VerifyCredential::class)->execute($account->id, CredentialType::MAGIC_LINK, ['token' => $token], new VerifiedFactors());

        $this->assertTrue($first->isSuccess());
        $this->assertFalse($second->isSuccess());
    }

    public function test_cannotIssueTokenWhenMagicLinkDisabled(): void {
        $account = $this->makeAccount();

        $this->expectException(RuntimeException::class);
        app(IssueMagicLink::class)->execute($account->id);
    }

    public function test_cannotRemoveLastCredential(): void {
        $account = $this->makeAccount();
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->expectException(RuntimeException::class);
        app(RemoveCredential::class)->execute($account->id, CredentialType::PASSWORD);
    }

    public function test_removesCredentialWhenAnotherRemains(): void {
        $account = $this->makeAccount();
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        app(EnableMagicLink::class)->execute($account->id);

        app(RemoveCredential::class)->execute($account->id, CredentialType::MAGIC_LINK);

        $factors = new VerifiedFactors();
        $result = app(VerifyCredential::class)->execute($account->id, CredentialType::MAGIC_LINK, ['token' => 'whatever'], $factors);

        $this->assertFalse($result->isSuccess());
    }
}
