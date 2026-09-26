<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Infrastructure\AuthCodeModel;
use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 引き取り前のサービスアカウントは、発行元以外のサービスへ入れない
class UnclaimedServiceAccountAuthorizeTest extends TestCase {
    use RefreshDatabase;

    private const REDIRECT_URI = 'https://rp.example.com/callback';

    /**
     * @param string $id クライアントID
     * @return OAuthClientModel
     */
    private function makeClient(string $id): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => $id,
            'name' => $id,
            'redirect_uris' => [self::REDIRECT_URI],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'skips_consent' => true,
        ]);
    }

    /**
     * 発行元のサービスアカウントとしてログインする。
     *
     * @param OAuthClientModel $issuer 発行元
     * @return string 認証主体のID
     */
    private function loginServiceAccount(OAuthClientModel $issuer): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::SERVICE, 'svc@example.com', null);
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        ServiceAccountModel::create([
            'client_id' => $issuer->id,
            'auth_identity_id' => $account->id,
            'service_user_id' => '248',
        ]);
        $this->post('/login', ['email' => 'svc@example.com', 'password' => 'correct-horse']);

        return $account->id;
    }

    /**
     * @param OAuthClientModel $client 接続先
     * @return string
     */
    private function authorizeUrl(OAuthClientModel $client): string {
        return '/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid profile',
            'state' => 'xyz',
        ]);
    }

    public function test_allowsTheIssuingService(): void {
        $issuer = $this->makeClient('dokufarm');
        $this->loginServiceAccount($issuer);

        $this->get($this->authorizeUrl($issuer))->assertRedirectContains('code=');
    }

    // 許すと同じ器に別サービスの行が作られ、1サービス1アカウントの前提が崩れる
    public function test_rejectsAnotherServiceWithoutCreatingALink(): void {
        $issuer = $this->makeClient('dokufarm');
        $other = $this->makeClient('wikichree');
        $accountId = $this->loginServiceAccount($issuer);

        $this->get($this->authorizeUrl($other))->assertOk();

        $this->assertSame(0, AuthCodeModel::query()->count());
        $this->assertFalse(ServiceAccountModel::query()
            ->where('client_id', $other->id)
            ->where('auth_identity_id', $accountId)
            ->exists());
    }
}
