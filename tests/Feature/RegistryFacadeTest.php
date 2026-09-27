<?php
namespace Tests\Feature;

use App\Modules\Credential\Domain\CredentialRegistry as CredentialList;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Facades\CredentialRegistry;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry as IdpList;
use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Provider\Domain\Claims\ClaimsResolver;
use App\Modules\Provider\Domain\Claims\ScopeRegistry as ScopeList;
use App\Modules\Provider\Facades\ScopeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// 各 Registry の Facade が、コンテナにある本物の一覧を指していること
class RegistryFacadeTest extends TestCase {
    use RefreshDatabase;

    #[TestDox('Facade で追加した scope が、本体の一覧から引ける')]
    public function test_scopeRegistry(): void {
        ScopeRegistry::register(new class implements ClaimsResolver {
            public function scope(): string {
                return 'wiki';
            }

            /**
             * @param AuthIdentity $account
             * @param string|null $serviceAccountId
             * @return array<string, mixed>
             */
            public function resolve(AuthIdentity $account, ?string $serviceAccountId): array {
                return ['wiki_name' => 'テスト'];
            }
        });

        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');

        $this->assertSame('テスト', app(ScopeList::class)->claimsFor($account, ['wiki'])['wiki_name'] ?? null);
    }

    #[TestDox('Facade から引く一覧は、本体が使う一覧と同じもの')]
    public function test_facadesShareTheContainerInstance(): void {
        $this->assertSame(app(CredentialList::class)->get(CredentialType::PASSWORD), CredentialRegistry::get(CredentialType::PASSWORD));
        $this->assertSame(app(IdpList::class)->names(), ExternalIdpRegistry::names());
    }
}
