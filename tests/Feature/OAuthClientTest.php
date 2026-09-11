<?php
namespace Tests\Feature;

use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

// サービス登録と、リダイレクト先・スコープの照合
class OAuthClientTest extends TestCase {
    use RefreshDatabase;

    /**
     * artisan() は PendingCommand|int を返すので、流れるような検証の前に絞る。
     *
     * @param string $command コマンド名
     * @param array<string, mixed> $arguments 引数とオプション
     * @return PendingCommand
     */
    private function runCommand(string $command, array $arguments): PendingCommand {
        $pending = $this->artisan($command, $arguments);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return OAuthClientModel
     */
    private function makeClient(array $overrides = []): OAuthClientModel {
        return OAuthClientModel::create(array_merge([
            'id' => 'test-client',
            'name' => 'テストサービス',
            'redirect_uris' => ['https://example.com/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::APPROVED,
        ], $overrides));
    }

    public function test_registersClientViaCommand(): void {
        $this->runCommand('chreeid:register-client', [
            'name' => 'DokuFarm',
            'redirect-uri' => ['https://doku.example.com/callback'],
            '--trust' => 'official',
        ])->assertSuccessful();

        $client = OAuthClientModel::query()->where('name', 'DokuFarm')->firstOrFail();

        $this->assertSame(ServiceTrust::OFFICIAL, $client->trust);
        $this->assertNotNull($client->secret_hash);
    }

    public function test_rejectsInvalidTrust(): void {
        $this->runCommand('chreeid:register-client', [
            'name' => 'Bad',
            'redirect-uri' => ['https://example.com/callback'],
            '--trust' => 'nonsense',
        ])->assertFailed();
    }

    public function test_publicClientHasNoSecret(): void {
        $this->runCommand('chreeid:register-client', [
            'name' => 'SPA',
            'redirect-uri' => ['https://example.com/callback'],
            '--public' => true,
        ])->assertSuccessful();

        $client = OAuthClientModel::query()->where('name', 'SPA')->firstOrFail();

        $this->assertNull($client->secret_hash);
        $this->assertTrue($client->requiresPkce());
    }

    public function test_matchesRedirectUriExactly(): void {
        $client = $this->makeClient();

        $this->assertTrue($client->allowsRedirectUri('https://example.com/callback'));
        $this->assertFalse($client->allowsRedirectUri('https://example.com/callback/'));
        $this->assertFalse($client->allowsRedirectUri('https://example.com/callback?x=1'));
        $this->assertFalse($client->allowsRedirectUri('https://evil.example.com/callback'));
    }

    public function test_rejectsScopesOutsideAllowedSet(): void {
        $client = $this->makeClient(['scopes' => 'openid profile']);

        $this->assertTrue($client->allowsScopes(['openid', 'profile']));
        $this->assertFalse($client->allowsScopes(['openid', 'email']));
    }

    // 同意の省略は信頼状態とは別の設定。承認済みでも省略したいサービスがある
    public function test_consentSkippingIsIndependentOfTrust(): void {
        $approved = OAuthClientModel::create([
            'id' => 'approved-skipping',
            'name' => '承認済みだが同意は省略',
            'redirect_uris' => ['https://rp.example.com/callback'],
            'scopes' => 'openid',
            'is_confidential' => true,
            'trust' => ServiceTrust::APPROVED,
            'skips_consent' => true,
        ]);

        $this->assertTrue($approved->skips_consent);
        $this->assertSame(ServiceTrust::APPROVED, $approved->trust);
    }

    public function test_disabledServiceIsNotUsable(): void {
        $this->assertFalse(ServiceTrust::DISABLED->isUsable());
        $this->assertTrue(ServiceTrust::UNAPPROVED->isUsable());
    }
}
