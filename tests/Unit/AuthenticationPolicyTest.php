<?php
namespace Tests\Unit;

use App\Modules\Credential\Domain\AuthenticationPolicy;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactor;
use App\Modules\Credential\Domain\VerifiedFactors;
use PHPUnit\Framework\TestCase;

// 認証成立の判定に関する単体テスト
class AuthenticationPolicyTest extends TestCase {
    private AuthenticationPolicy $policy;

    #[\Override]
    protected function setUp(): void {
        $this->policy = new AuthenticationPolicy();
    }

    /**
     * 2FA を有効にしていなければパスワード1つで通る
     */
    public function test_2FAなしはパスワード単体で成立する(): void {
        $factors = new VerifiedFactors();
        $factors->add(new VerifiedFactor(CredentialType::PASSWORD, false));

        $this->assertTrue($this->policy->isSatisfied(false, $factors));
    }

    /**
     * 2FA が有効なら1要素では足りない
     */
    public function test_2FAありはパスワード単体では成立しない(): void {
        $factors = new VerifiedFactors();
        $factors->add(new VerifiedFactor(CredentialType::PASSWORD, false));

        $this->assertFalse($this->policy->isSatisfied(true, $factors));
    }

    /**
     * パスワード + TOTP で2要素を満たす
     */
    public function test_2FAありでも2要素揃えば成立する(): void {
        $factors = new VerifiedFactors();
        $factors->add(new VerifiedFactor(CredentialType::PASSWORD, false));
        $factors->add(new VerifiedFactor(CredentialType::TOTP, false));

        $this->assertTrue($this->policy->isSatisfied(true, $factors));
    }

    /**
     * パスキーはそれ自体が多要素なので、2FA が有効でも単体で通る
     */
    public function test_パスキーは2FAありでも単体で成立する(): void {
        $factors = new VerifiedFactors();
        $factors->add(new VerifiedFactor(CredentialType::PASSKEY, true));

        $this->assertTrue($this->policy->isSatisfied(true, $factors));
    }

    /**
     * 同じ方式を2回通しても2要素にはならない
     */
    public function test_同じ方式の重複は1要素として数える(): void {
        $factors = new VerifiedFactors();
        $factors->add(new VerifiedFactor(CredentialType::PASSWORD, false));
        $factors->add(new VerifiedFactor(CredentialType::PASSWORD, false));

        $this->assertSame(1, $factors->count());
        $this->assertFalse($this->policy->isSatisfied(true, $factors));
    }

    /**
     * 何も検証できていなければ成立しない
     */
    public function test_要素が0件なら成立しない(): void {
        $factors = new VerifiedFactors();

        $this->assertFalse($this->policy->isSatisfied(false, $factors));
    }
}
