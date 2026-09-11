<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Infrastructure\AuthCodeModel;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

// 1人が同じサービスに複数のサービスアカウントを持つとき、どれとして入るかを選ばせる
class ChooseServiceAccountTest extends TestCase {
    use RefreshDatabase;

    private const REDIRECT_URI = 'https://rp.example.com/callback';

    /**
     * @return OAuthClientModel
     */
    private function client(): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => 'wikichree',
            'name' => 'WikiChree',
            'redirect_uris' => [self::REDIRECT_URI],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'skips_consent' => true,
        ]);
    }

    /**
     * @return string 認証主体のID (ULID)
     */
    private function signIn(): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        return $account->id;
    }

    /**
     * @param OAuthClientModel $client サービス
     * @param string $identityId 認証主体のID
     * @param string $serviceUserId サービス側での識別子
     * @return ServiceAccountModel
     */
    private function serviceAccount(OAuthClientModel $client, string $identityId, string $serviceUserId): ServiceAccountModel {
        return ServiceAccountModel::create([
            'client_id' => $client->id,
            'auth_identity_id' => $identityId,
            'service_user_id' => $serviceUserId,
            'sub' => Str::lower(Str::ulid()->toString()),
        ]);
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function authorizeQuery(OAuthClientModel $client, array $overrides = []): array {
        return array_merge([
            'client_id' => $client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid profile',
            'state' => 'xyz',
        ], $overrides);
    }

    // 1つしか無ければ聞かない
    public function test_doesNotAskWhenThereIsOnlyOne(): void {
        $client = $this->client();
        $identityId = $this->signIn();
        $this->serviceAccount($client, $identityId, 'wiki-a');

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)))
            ->assertRedirectContains(self::REDIRECT_URI);
    }

    public function test_asksWhenThereAreSeveral(): void {
        $client = $this->client();
        $identityId = $this->signIn();
        $this->serviceAccount($client, $identityId, 'wiki-a');
        $this->serviceAccount($client, $identityId, 'wiki-b');

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Oauth/ChooseAccount')
                ->has('accounts', 2));
    }

    // 黙ってどれかを選ぶとログインさせてしまうので、コードは出さない
    public function test_issuesNoCodeWhileItIsAmbiguous(): void {
        $client = $this->client();
        $identityId = $this->signIn();
        $this->serviceAccount($client, $identityId, 'wiki-a');
        $this->serviceAccount($client, $identityId, 'wiki-b');

        $this->get('/oauth/authorize?' . http_build_query($this->authorizeQuery($client)));

        $this->assertSame(0, AuthCodeModel::query()->count());
    }

    public function test_issuesTheCodeForTheChosenAccount(): void {
        $client = $this->client();
        $identityId = $this->signIn();
        $this->serviceAccount($client, $identityId, 'wiki-a');
        $chosen = $this->serviceAccount($client, $identityId, 'wiki-b');

        $this->post('/oauth/authorize/approve', $this->authorizeQuery($client, ['service_account_id' => $chosen->id]))
            ->assertRedirectContains(self::REDIRECT_URI);

        $this->assertSame($chosen->id, AuthCodeModel::query()->firstOrFail()->service_account_id);
    }

    // 画面から戻ってきた値は信用しない。他人のサービスアカウントを指されても通さない
    public function test_refusesAnAccountThatIsNotTheirs(): void {
        $client = $this->client();
        $identityId = $this->signIn();
        $this->serviceAccount($client, $identityId, 'wiki-a');
        $this->serviceAccount($client, $identityId, 'wiki-b');

        $other = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'other@example.com', '別人');
        $stranger = $this->serviceAccount($client, $other->id, 'wiki-c');

        $this->post('/oauth/authorize/approve', $this->authorizeQuery($client, ['service_account_id' => $stranger->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Oauth/ChooseAccount'));

        $this->assertSame(0, AuthCodeModel::query()->count());
    }
}
