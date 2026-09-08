<?php
namespace Tests\Feature;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\ExternalLogin\Application\LinkExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentityConflict;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 外部 IdP との紐付け (ChreeID が RP 側)
class ExternalLoginTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param string $subject IdP 側のID
     * @param string|null $email
     * @param bool $verified IdP がメールを検証済みとしているか
     * @return ExternalIdentity
     */
    private function identity(string $subject, ?string $email, bool $verified = true): ExternalIdentity {
        return new ExternalIdentity('google', $subject, $email, $verified, 'グーグル太郎');
    }

    public function test_createsAccountWhenNothingMatches(): void {
        $accountId = app(LinkExternalIdentity::class)->execute($this->identity('g-1', 'new@example.com'));

        $account = app(ChreeAccountRepository::class)->findById($accountId);
        $this->assertNotNull($account);
        $this->assertSame('new@example.com', $account->email);
        $this->assertSame(AccountOrigin::USER, $account->origin);
    }

    public function test_reusesAccountLinkedByExternalId(): void {
        $link = app(LinkExternalIdentity::class);
        $first = $link->execute($this->identity('g-1', 'user@example.com'));
        $second = $link->execute($this->identity('g-1', 'changed@example.com'));

        $this->assertSame($first, $second);
        $this->assertSame(1, CredentialModel::query()->where('type', CredentialType::OAUTH)->count());
    }

    /**
     * IdP 側でメールが検証済みなら、既存アカウントに紐付ける
     */
    public function test_linksToExistingAccountWhenEmailVerified(): void {
        $account = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'user@example.com', '既存');

        $accountId = app(LinkExternalIdentity::class)->execute($this->identity('g-1', 'user@example.com', true));

        $this->assertSame($account->id, $accountId);
    }

    /**
     * 未検証のメールで自動紐付けすると、被害者のメールで作った IdP アカウントから乗っ取れる。
     * かといって黙って2つ目を作らず、明示的に断る
     */
    public function test_refusesToLinkWhenEmailIsUnverified(): void {
        app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'user@example.com', '既存');

        $this->expectException(ExternalIdentityConflict::class);
        app(LinkExternalIdentity::class)->execute($this->identity('g-1', 'user@example.com', false));
    }

    /**
     * 衝突しないなら、未検証でも新規アカウントは作れる
     */
    public function test_createsAccountWhenEmailIsUnverifiedAndUnused(): void {
        $accountId = app(LinkExternalIdentity::class)->execute($this->identity('g-1', 'fresh@example.com', false));

        $this->assertNotNull(app(ChreeAccountRepository::class)->findById($accountId));
    }

    public function test_storesProviderPrefixedIdentifier(): void {
        app(LinkExternalIdentity::class)->execute($this->identity('g-1', 'user@example.com'));

        $this->assertSame(1, CredentialModel::query()
            ->where('type', CredentialType::OAUTH)
            ->where('identifier', 'google:g-1')
            ->count());
    }

    public function test_registersGoogleIdp(): void {
        $this->assertSame(['google'], app(ExternalIdpRegistry::class)->names());
    }

    public function test_redirectsToProvider(): void {
        config(['services.google.client_id' => 'test-client-id']);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirectContains('accounts.google.com');
        $response->assertRedirectContains('nonce=');
        $response->assertRedirectContains('state=');
    }

    public function test_rejectsUnknownProvider(): void {
        $this->get('/auth/unknown/redirect')->assertRedirect('/login');
    }

    /**
     * state が一致しないリクエストは第三者に開始させられた可能性がある
     */
    public function test_rejectsCallbackWithWrongState(): void {
        $this->get('/auth/google/redirect');

        $this->get('/auth/google/callback?code=abc&state=wrong')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }

    public function test_rejectsCallbackWithoutSession(): void {
        $this->get('/auth/google/callback?code=abc&state=whatever')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }
}
