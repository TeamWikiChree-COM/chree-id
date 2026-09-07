<?php
namespace Tests\Unit;

use App\Modules\Credential\Domain\CredentialRegistry;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\Verifiers\PasswordVerifier;
use PHPUnit\Framework\TestCase;

// 認証タイプの登録に関する単体テスト
class CredentialRegistryTest extends TestCase {
    /**
     * 登録した認証タイプを取得するテスト
     */
    public function test_getRegisteredVerifier(): void {
        $registry = new CredentialRegistry();
        $registry->register(new PasswordVerifier());

        $this->assertNotNull($registry->get(CredentialType::PASSWORD));
    }

    /**
     * 登録されていない認証タイプを取得しようとすると null が返るテスト
     */
    public function test_getUnregisteredVerifier(): void {
        $registry = new CredentialRegistry();
        $registry->register(new PasswordVerifier());

        $this->assertNull($registry->get(CredentialType::PASSKEY));
    }
}
