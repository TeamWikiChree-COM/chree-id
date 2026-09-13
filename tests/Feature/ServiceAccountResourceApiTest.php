<?php
namespace Tests\Feature;

use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// /api/v1/service-accounts/{serviceUserId}。処理の中身は ServiceAccountApiTest が見ている。
// ここでは経路の形 (メソッドとステータス) を見る
class ServiceAccountResourceApiTest extends TestCase {
    use RefreshDatabase;

    private const SECRET = 'service-secret';
    private OAuthClientModel $client;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();

        $this->client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', self::SECRET),
            'name' => 'DokuFarm',
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'can_provision' => true,
        ]);
    }

    /**
     * @param string $method HTTP メソッド
     * @param string $path service-accounts/ 以下
     * @param array<string, mixed> $data
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function send(string $method, string $path, array $data = []): TestResponse {
        return $this->withBasicAuth($this->client->id, self::SECRET)
            ->json($method, "/api/v1/service-accounts/{$path}", $data);
    }

    public function test_putIssuesIdempotently(): void {
        $first = $this->send('PUT', '42', ['email' => 'user@example.com'])->assertOk()->json('sub');

        $this->send('PUT', '42', ['email' => 'user@example.com'])->assertOk()->assertJsonPath('sub', $first);
    }

    public function test_getDescribesTheUser(): void {
        $this->send('GET', '42')->assertNotFound()->assertJsonPath('error', 'unknown_service_user');

        $this->send('PUT', '42');
        $this->send('GET', '42')->assertOk()->assertJsonPath('migrated', false);
    }

    public function test_deleteAnswersNoContentThenNotFound(): void {
        $this->send('PUT', '42');

        $this->send('DELETE', '42')->assertNoContent();
        $this->send('DELETE', '42')->assertNotFound();
    }

    public function test_putPasswordAnswersNoContent(): void {
        $this->send('PUT', '42');

        $this->send('PUT', '42/password', ['password_hash' => password_hash('correct-horse', PASSWORD_BCRYPT)])
            ->assertNoContent();
    }

    public function test_postClaimTicketAnswersCreated(): void {
        $this->send('PUT', '42');

        $this->send('POST', '42/claim-tickets')->assertCreated()->assertJsonStructure(['claim_url', 'expires_at']);
    }

    public function test_rejectsAnOverlongIdentifierInThePath(): void {
        $this->send('GET', str_repeat('a', 191))
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_refusesWithoutClientAuthentication(): void {
        $this->getJson('/api/v1/service-accounts/42')->assertUnauthorized();
    }
}
