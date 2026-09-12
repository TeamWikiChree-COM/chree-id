<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\ExternalLogin\Application\LinkExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentityConflict;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 設定画面からの外部アカウント連携
class ExternalConnectionTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param bool $withPassword 他の認証手段を持たせるか
     * @return string アカウントID (ULID)
     */
    private function login(bool $withPassword = true): string {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', 'テスト');

        if ($withPassword) app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->withSession(['chreeid.account_id' => $account->id]);

        return $account->id;
    }

    /**
     * @param string $subject IdP 側のID
     * @return ExternalIdentity
     */
    private function identity(string $subject): ExternalIdentity {
        return new ExternalIdentity('google', $subject, 'user@example.com', true, 'グーグル太郎');
    }

    public function test_requiresLogin(): void {
        $this->get('/settings/connections')->assertRedirect('/login');
    }

    public function test_showsConnections(): void {
        $accountId = $this->login();
        app(LinkExternalIdentity::class)->linkTo($accountId, $this->identity('g-1'));

        $this->get('/settings/connections')->assertOk();
    }

    public function test_linksToLoggedInAccount(): void {
        $accountId = $this->login();

        app(LinkExternalIdentity::class)->linkTo($accountId, $this->identity('g-1'));

        $this->assertSame(1, CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::OAUTH)
            ->count());
    }

    /**
     * 同じ外部アカウントを二重に足させない
     */
    public function test_rejectsDuplicateLink(): void {
        $accountId = $this->login();
        app(LinkExternalIdentity::class)->linkTo($accountId, $this->identity('g-1'));

        $this->expectException(ExternalIdentityConflict::class);
        app(LinkExternalIdentity::class)->linkTo($accountId, $this->identity('g-1'));
    }

    public function test_disconnects(): void {
        $accountId = $this->login();
        app(LinkExternalIdentity::class)->linkTo($accountId, $this->identity('g-1'));

        $row = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::OAUTH)
            ->firstOrFail();

        $this->post('/settings/connections/remove', ['id' => $row->id])
            ->assertRedirect('/settings/connections');

        $this->assertSame(0, CredentialModel::query()->where('id', $row->id)->count());
    }

    /**
     * 唯一の認証手段だった場合、切るとログインできなくなる
     */
    public function test_cannotDisconnectLastCredential(): void {
        $accountId = $this->login(withPassword: false);
        app(LinkExternalIdentity::class)->linkTo($accountId, $this->identity('g-1'));

        $row = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::OAUTH)
            ->firstOrFail();

        $this->post('/settings/connections/remove', ['id' => $row->id])
            ->assertSessionHasErrors('provider');

        $this->assertSame(1, CredentialModel::query()->where('id', $row->id)->count());
    }

    /**
     * 他人の認証手段を指しても消えない
     */
    public function test_cannotDisconnectCredentialOfAnotherAccount(): void {
        $this->login();

        $other = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'other@example.com', '別人');
        app(SetPassword::class)->execute($other->id, 'correct-horse');
        app(LinkExternalIdentity::class)->linkTo($other->id, $this->identity('g-victim'));

        $row = CredentialModel::query()
            ->where('auth_identity_id', $other->id)
            ->where('type', CredentialType::OAUTH)
            ->firstOrFail();

        $this->post('/settings/connections/remove', ['id' => $row->id])
            ->assertSessionHasErrors('provider');

        $this->assertSame(1, CredentialModel::query()->where('id', $row->id)->count());
    }
}
