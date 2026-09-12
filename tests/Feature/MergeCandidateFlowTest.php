<?php
namespace Tests\Feature;

use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Audit\Infrastructure\AuditEventModel;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\AuthIdentityAliasModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// 候補として挙がった別アカウントを、本人が寄せる経路
//
// **同じメールアドレスであることは実行の根拠にならない。** 相手側を証明できたときだけ通る。
class MergeCandidateFlowTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
    }

    /**
     * 同じアドレスの2つのアカウントを作り、片方でログインする。
     *
     * @return array{target: string, source: string}
     */
    private function bothSides(): array {
        $accounts = app(AuthIdentityRepository::class);

        $target = $accounts->create(AccountOrigin::USER, 'user@example.com', 'こちら');
        app(SetPassword::class)->execute($target->id, 'correct-horse');
        app(UserAccounts::class)->ensure($target->id);

        $source = $accounts->create(AccountOrigin::USER, 'user@example.com', 'あちら');
        app(SetPassword::class)->execute($source->id, 'other-password');

        $this->withSession(['chreeid.account_id' => $target->id]);

        return ['target' => $target->id, 'source' => $source->id];
    }

    public function test_requiresLogin(): void {
        $this->get('/settings/merge/01ABC')->assertRedirect('/login');
    }

    public function test_showsCandidate(): void {
        $ids = $this->bothSides();

        $this->get("/settings/merge/{$ids['source']}")->assertOk();
    }

    /**
     * 候補でないアカウントを指しても画面を出さない
     */
    public function test_rejectsAccountThatIsNotACandidate(): void {
        $this->bothSides();
        $stranger = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::USER, 'stranger@example.com', '別人');

        $this->get("/settings/merge/{$stranger->id}")->assertRedirect('/');
    }

    public function test_mergesWhenPasswordProven(): void {
        $ids = $this->bothSides();

        $this->post('/settings/merge', [
            'candidate' => $ids['source'],
            'proof' => 'password',
            'secret' => 'other-password',
        ])->assertRedirect('/');

        // 寄せ元は停止され、転送表が残る
        $source = app(AuthIdentityRepository::class)->findById($ids['source']);
        $this->assertNotNull($source);
        $this->assertTrue($source->isSuspended());
        $this->assertSame(1, AuthIdentityAliasModel::query()
            ->where('legacy_id', $ids['source'])
            ->where('current_id', $ids['target'])
            ->count());
    }

    /**
     * **これが実行の根拠。** 相手のパスワードを知らない人には通させない
     */
    public function test_refusesWhenProofIsWrong(): void {
        $ids = $this->bothSides();

        $this->post('/settings/merge', [
            'candidate' => $ids['source'],
            'proof' => 'password',
            'secret' => 'wrong',
        ])->assertSessionHasErrors('secret');

        $source = app(AuthIdentityRepository::class)->findById($ids['source']);
        $this->assertNotNull($source);
        $this->assertFalse($source->isSuspended());
    }

    /**
     * メールが一致しているだけでは統合されない (候補に挙がるだけ)
     */
    public function test_doesNotMergeWithoutProof(): void {
        $ids = $this->bothSides();

        $this->post('/settings/merge', ['candidate' => $ids['source'], 'proof' => 'password'])
            ->assertSessionHasErrors('secret');

        $source = app(AuthIdentityRepository::class)->findById($ids['source']);
        $this->assertNotNull($source);
        $this->assertFalse($source->isSuspended());
    }

    public function test_acceptsTotpAsProof(): void {
        $accounts = app(AuthIdentityRepository::class);

        $target = $accounts->create(AccountOrigin::USER, 'user@example.com', 'こちら');
        app(SetPassword::class)->execute($target->id, 'correct-horse');
        app(UserAccounts::class)->ensure($target->id);

        $source = $accounts->create(AccountOrigin::USER, 'user@example.com', 'あちら');
        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($source->id, $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));

        $this->withSession(['chreeid.account_id' => $target->id]);

        $this->post('/settings/merge', [
            'candidate' => $source->id,
            'proof' => 'totp',
            'secret' => app(Totp::class)->at($secret, intdiv(time(), 30)),
        ])->assertRedirect('/');

        $merged = $accounts->findById($source->id);
        $this->assertNotNull($merged);
        $this->assertTrue($merged->isSuspended());
    }

    /**
     * 選んだ認証手段だけが寄せ先へ移る
     */
    public function test_carriesChosenCredentials(): void {
        $accounts = app(AuthIdentityRepository::class);

        $target = $accounts->create(AccountOrigin::USER, 'user@example.com', 'こちら');
        app(UserAccounts::class)->ensure($target->id);

        $source = $accounts->create(AccountOrigin::USER, 'user@example.com', 'あちら');
        app(SetPassword::class)->execute($source->id, 'other-password');

        $password = CredentialModel::query()
            ->where('auth_identity_id', $source->id)
            ->where('type', CredentialType::PASSWORD)
            ->firstOrFail();

        $this->withSession(['chreeid.account_id' => $target->id]);

        $this->post('/settings/merge', [
            'candidate' => $source->id,
            'proof' => 'password',
            'secret' => 'other-password',
            'credentials' => [$password->id],
        ])->assertRedirect('/');

        $this->assertSame($target->id, CredentialModel::query()->findOrFail($password->id)->auth_identity_id);
    }

    public function test_recordsMerge(): void {
        $ids = $this->bothSides();

        $this->post('/settings/merge', [
            'candidate' => $ids['source'],
            'proof' => 'password',
            'secret' => 'other-password',
        ]);

        $this->assertSame(1, AuditEventModel::query()
            ->where('auth_identity_id', $ids['target'])
            ->where('action', AuditAction::ACCOUNT_MERGED->value)
            ->count());
    }
}
