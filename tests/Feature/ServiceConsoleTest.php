<?php
namespace Tests\Feature;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 第三者が自分のサービスを登録する導線
//
// **ここで作れるのは未承認のものだけ。** 信頼状態・同意省略・発行権限は運営しか動かせない。
class ServiceConsoleTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param string $email 連絡先
     * @return string アカウントID (ULID)
     */
    private function login(string $email = 'owner@example.com'): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, $email, '登録者');
        $this->withSession(['chreeid.account_id' => $account->id]);

        return $account->id;
    }

    /**
     * @param string $ownerId 持ち主
     * @return OAuthClientModel
     */
    private function service(string $ownerId): OAuthClientModel {
        return OAuthClientModel::query()->create([
            'id' => 'svc-' . uniqid(),
            'name' => 'テストサービス',
            'redirect_uris' => ['https://example.com/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::UNAPPROVED,
            'owner_id' => $ownerId,
        ]);
    }

    public function test_requiresLogin(): void {
        $this->get('/services')->assertRedirect('/login');
        $this->post('/services/register', ['name' => 'x'])->assertRedirect('/login');
    }

    public function test_registersAsUnapproved(): void {
        $ownerId = $this->login();

        $this->post('/services/register', [
            'name' => 'みんなの掲示板',
            'redirect_uris' => ['https://example.com/callback'],
        ])->assertRedirect('/services');

        $client = OAuthClientModel::query()->where('owner_id', $ownerId)->firstOrFail();
        $this->assertSame(ServiceTrust::UNAPPROVED, $client->trust);
        // **未承認のものに同意省略や発行権限を持たせない**
        $this->assertFalse($client->skips_consent);
        $this->assertFalse($client->can_provision);
    }

    public function test_showsSecretOnce(): void {
        $this->login();

        $this->post('/services/register', [
            'name' => 'みんなの掲示板',
            'redirect_uris' => ['https://example.com/callback'],
        ]);

        $issued = session('issuedSecret');
        $this->assertIsArray($issued);
        $this->assertIsString($issued['secret'] ?? null);
    }

    public function test_rejectsInvalidRedirectUri(): void {
        $this->login();

        $this->post('/services/register', ['name' => 'x', 'redirect_uris' => ['not-a-url']])
            ->assertSessionHasErrors('redirect_uris.0');
    }

    public function test_editsOwnService(): void {
        $ownerId = $this->login();
        $service = $this->service($ownerId);

        $this->post("/services/{$service->id}/edit", [
            'name' => '名前を変えた',
            'redirect_uris' => ['https://example.com/callback'],
            'settings_url' => 'https://example.com/settings',
        ])->assertRedirect('/services');

        $updated = OAuthClientModel::query()->findOrFail($service->id);
        $this->assertSame('名前を変えた', $updated->name);
        $this->assertSame('https://example.com/settings', $updated->settings_url);
    }

    /**
     * 他人のサービスは触れない
     */
    public function test_cannotEditSomeoneElsesService(): void {
        $other = $this->login('other@example.com');
        $service = $this->service($other);

        $this->login('me@example.com');

        $this->post("/services/{$service->id}/edit", [
            'name' => '乗っ取り',
            'redirect_uris' => ['https://evil.example.com/callback'],
        ])->assertRedirect('/services');

        $this->assertSame('テストサービス', OAuthClientModel::query()->findOrFail($service->id)->name);
    }

    public function test_requestsReview(): void {
        $ownerId = $this->login();
        $service = $this->service($ownerId);

        $this->post("/services/{$service->id}/review")->assertRedirect('/services');

        $this->assertNotNull(OAuthClientModel::query()->findOrFail($service->id)->review_requested_at);
    }

    /**
     * 申請しただけでは信頼状態は動かない
     */
    public function test_reviewDoesNotApprove(): void {
        $ownerId = $this->login();
        $service = $this->service($ownerId);

        $this->post("/services/{$service->id}/review");

        $this->assertSame(ServiceTrust::UNAPPROVED, OAuthClientModel::query()->findOrFail($service->id)->trust);
    }
}
