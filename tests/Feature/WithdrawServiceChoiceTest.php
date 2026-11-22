<?php
namespace Tests\Feature;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Application\DeliverServiceDeletions;
use App\Modules\Linking\Domain\ServiceDeletionStatus;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Linking\Infrastructure\ServiceDeletionRequestModel;
use App\Modules\Linking\Mail\ServiceDeletionRefusedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// 退会のときに、連携サービスごとに削除を依頼するか解放するかを選ぶ
class WithdrawServiceChoiceTest extends TestCase {
    use RefreshDatabase;

    private const DELETION_URI = 'https://wiki.example.com/chreeid/withdrawal';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
        Mail::fake();
    }

    /**
     * @return string アカウントID (ULID)
     */
    private function login(): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $id = session('chreeid.account_id');
        $this->assertIsString($id);

        return $id;
    }

    /**
     * @param string $accountId 紐付けるアカウント
     * @param string|null $deletionUri サービスの削除依頼 URL
     * @return ServiceAccountModel
     */
    private function connect(string $accountId, ?string $deletionUri): ServiceAccountModel {
        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', 'x'),
            'name' => 'ExampleWiki',
            'redirect_uris' => ['https://wiki.example.com/cb'],
            'scopes' => 'openid',
            'is_confidential' => true,
            'deletion_request_uri' => $deletionUri,
        ]);

        return ServiceAccountModel::create([
            'client_id' => $client->id,
            'auth_identity_id' => $accountId,
            'sub' => 'sub-' . Str::random(8),
        ]);
    }

    /**
     * @param string $accountId
     * @return string 持っていけるパスワードの認証手段のID
     */
    private function passwordId(string $accountId): string {
        return CredentialModel::query()->where('auth_identity_id', $accountId)->where('type', 'password')->valueOrFail('id');
    }

    public function test_deletionSendsASignedRequestRightAway(): void {
        Http::fake([self::DELETION_URI => Http::response(['accepted' => true])]);
        $accountId = $this->login();
        $service = $this->connect($accountId, self::DELETION_URI);

        $this->post('/settings/withdraw', [
            'understood' => true,
            'services' => [$service->id => ['action' => 'delete']],
        ])->assertRedirect('/login');

        Http::assertSent(function (Request $request) use ($service): bool {
            $claims = $this->claims((string) $request['withdrawal_token']);

            return $request->url() === self::DELETION_URI
                && $claims['sub'] === $service->sub
                && $claims['aud'] === $service->client_id
                && $claims['events']['urn:chreeid:event:account-withdrawn']['action'] === 'delete';
        });

        $this->assertSame(ServiceDeletionStatus::ACCEPTED, ServiceDeletionRequestModel::query()->sole()->status);
    }

    public function test_releaseSplitsTheServiceWithTheChosenCredentials(): void {
        $accountId = $this->login();
        $service = $this->connect($accountId, null);

        $this->post('/settings/withdraw', [
            'understood' => true,
            'services' => [$service->id => ['action' => 'release', 'credentials' => [$this->passwordId($accountId)]]],
        ])->assertRedirect('/login');

        $moved = ServiceAccountModel::query()->findOrFail($service->id);
        $this->assertNotSame($accountId, $moved->auth_identity_id);
        $this->assertSame($service->sub, $moved->sub);
        $this->assertFalse(app(AuthIdentityRepository::class)->findById($moved->auth_identity_id)?->isDeleted());
        $this->assertTrue(CredentialModel::query()->where('auth_identity_id', $moved->auth_identity_id)->exists());
    }

    // 押し間違いを防ぐため、選ばれていないサービスがあれば退会させない
    public function test_requiresAChoiceForEveryService(): void {
        $accountId = $this->login();
        $service = $this->connect($accountId, self::DELETION_URI);

        $this->post('/settings/withdraw', ['understood' => true])->assertSessionHasErrors("services.{$service->id}");

        $this->assertFalse(app(AuthIdentityRepository::class)->findById($accountId)?->isDeleted());
    }

    // 依頼 URL の無いサービスには削除を選ばせない
    public function test_rejectsDeletionForServicesWithoutAnEndpoint(): void {
        $accountId = $this->login();
        $service = $this->connect($accountId, null);

        $this->post('/settings/withdraw', [
            'understood' => true,
            'services' => [$service->id => ['action' => 'delete']],
        ])->assertSessionHasErrors("services.{$service->id}");

        $this->assertSame(0, ServiceDeletionRequestModel::query()->count());
    }

    public function test_releaseNeedsAtLeastOneCredential(): void {
        $accountId = $this->login();
        $service = $this->connect($accountId, null);

        $this->post('/settings/withdraw', [
            'understood' => true,
            'services' => [$service->id => ['action' => 'release', 'credentials' => []]],
        ])->assertSessionHasErrors("services.{$service->id}");
    }

    // 届かなくても退会は止めず、あとで再送する
    public function test_unreachableServicesAreRetriedLater(): void {
        Http::fake([self::DELETION_URI => Http::sequence()->push('', 503)->push(['accepted' => true])]);
        $accountId = $this->login();
        $service = $this->connect($accountId, self::DELETION_URI);

        $this->post('/settings/withdraw', [
            'understood' => true,
            'services' => [$service->id => ['action' => 'delete']],
        ])->assertRedirect('/login');

        $request = ServiceDeletionRequestModel::query()->sole();
        $this->assertSame(ServiceDeletionStatus::PENDING, $request->status);

        $this->assertSame(1, app(DeliverServiceDeletions::class)->pending());
        $this->assertSame(ServiceDeletionStatus::ACCEPTED, $request->refresh()->status);
    }

    public function test_refusalIsMailedToTheWithdrawnAddress(): void {
        Http::fake([self::DELETION_URI => Http::response(['accepted' => false, 'reason' => 'Wiki が残っています'])]);
        $accountId = $this->login();
        $service = $this->connect($accountId, self::DELETION_URI);

        $this->post('/settings/withdraw', [
            'understood' => true,
            'services' => [$service->id => ['action' => 'delete']],
        ])->assertRedirect('/login');

        $this->assertSame(ServiceDeletionStatus::REFUSED, ServiceDeletionRequestModel::query()->sole()->status);
        Mail::assertSent(ServiceDeletionRefusedMail::class, fn (ServiceDeletionRefusedMail $mail): bool => $mail->hasTo('user@example.com'));
    }

    // 依頼を送ったあとで物理削除されても、依頼の記録は残る
    public function test_requestsSurviveThePurge(): void {
        Http::fake([self::DELETION_URI => Http::response('', 503)]);
        $accountId = $this->login();
        $service = $this->connect($accountId, self::DELETION_URI);

        $this->post('/settings/withdraw', [
            'understood' => true,
            'services' => [$service->id => ['action' => 'delete']],
        ]);

        $this->travel(\App\Modules\Identity\Application\PurgeDeletedAccounts::graceDays() + 1)->days();
        app(\App\Modules\Identity\Application\PurgeDeletedAccounts::class)->execute();

        $this->assertSame($service->sub, ServiceDeletionRequestModel::query()->sole()->sub);
    }

    /**
     * @param string $jwt
     * @return array<string, mixed>
     */
    private function claims(string $jwt): array {
        $payload = explode('.', $jwt)[1] ?? '';
        $decoded = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);

        return is_array($decoded) ? $decoded : [];
    }
}
