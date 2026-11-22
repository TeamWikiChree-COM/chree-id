<?php
namespace Tests\Feature;

use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

// サービス側で退会した利用者を、サービスに渡した sub で畳む
class DeactivateBySubjectTest extends TestCase {
    use RefreshDatabase;

    private const SECRET = 'service-secret';

    /**
     * @return OAuthClientModel
     */
    private function client(): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', self::SECRET),
            'name' => 'ExampleWiki',
            'redirect_uris' => ['https://wiki.example.com/cb'],
            'scopes' => 'openid',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'can_provision' => true,
        ]);
    }

    /**
     * @param OAuthClientModel $client
     * @param string $sub
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function deactivate(OAuthClientModel $client, string $sub): \Illuminate\Testing\TestResponse {
        return $this->deleteJson('/api/v1/subjects/' . rawurlencode($sub), [], [
            'Authorization' => 'Basic ' . base64_encode("{$client->id}:" . self::SECRET),
        ]);
    }

    // ChreeID の本体を持つ人は、そのサービスとの結び付きだけが外れる
    public function test_unlinksOnlyThatService(): void {
        $client = $this->client();
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(UserAccounts::class)->ensure($account->id);
        ServiceAccountModel::create(['client_id' => $client->id, 'auth_identity_id' => $account->id, 'sub' => 'oidc-sub']);

        $this->deactivate($client, 'oidc-sub')->assertNoContent();

        $this->assertFalse(ServiceAccountModel::query()->where('sub', 'oidc-sub')->exists());
        $this->assertNotNull(app(AuthIdentityRepository::class)->findById($account->id));
    }

    // そのサービスのためだけにあった ChreeID は残す理由が無い
    public function test_removesAnIdentityThatOnlyServedThatService(): void {
        $client = $this->client();
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::SERVICE, 'user@example.com', 'テスト');
        ServiceAccountModel::create(['client_id' => $client->id, 'auth_identity_id' => $account->id, 'sub' => 'oidc-sub']);

        $this->deactivate($client, 'oidc-sub')->assertNoContent();

        $this->assertNull(app(AuthIdentityRepository::class)->findById($account->id));
    }

    // 他のサービスの sub では畳めない
    public function test_ignoresSubjectsOfOtherServices(): void {
        $client = $this->client();
        $other = $this->client();
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::SERVICE, 'user@example.com', 'テスト');
        ServiceAccountModel::create(['client_id' => $other->id, 'auth_identity_id' => $account->id, 'sub' => 'oidc-sub']);

        $this->deactivate($client, 'oidc-sub')->assertNotFound();

        $this->assertTrue(ServiceAccountModel::query()->where('sub', 'oidc-sub')->exists());
    }
}
