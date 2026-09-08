<?php
namespace Tests\Feature;

use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\PendingRegistrationModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Illuminate\Support\Str;
use Tests\TestCase;

// 期限切れの登録申し込みとトークンの掃除
class PruneExpiredTokensTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param \Illuminate\Support\Carbon $expiresAt 失効日時
     * @return void
     */
    private function pendingRegistration(\Illuminate\Support\Carbon $expiresAt): void {
        PendingRegistrationModel::create([
            'email' => Str::random(8) . '@example.com',
            'display_name' => null,
            'password_hash' => 'dummy',
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param \Illuminate\Support\Carbon $expiresAt 失効日時
     * @param \Illuminate\Support\Carbon|null $usedAt 使用日時
     * @return void
     */
    private function token(string $accountId, \Illuminate\Support\Carbon $expiresAt, ?\Illuminate\Support\Carbon $usedAt = null): void {
        $row = OneTimeTokenModel::create([
            'chree_account_id' => $accountId,
            'token_hash' => hash('sha256', Str::random(64)),
            'purpose' => OneTimeTokenModel::PURPOSE_LOGIN,
            'expires_at' => $expiresAt,
        ]);

        if ($usedAt !== null) $row->forceFill(['used_at' => $usedAt])->save();
    }

    /**
     * artisan() は PendingCommand|int を返すので、コマンドとして扱えることを確かめてから実行する。
     *
     * @param int|null $days --days に渡す値
     * @return void
     */
    private function prune(?int $days = null): void {
        $command = $days === null
            ? $this->artisan('chreeid:prune-tokens')
            : $this->artisan('chreeid:prune-tokens', ['--days' => $days]);

        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();
    }

    public function test_deletesExpiredRegistrationsAndKeepsLiveOnes(): void {
        $this->pendingRegistration(now()->subHour());
        $this->pendingRegistration(now()->addHour());

        $this->prune();

        $this->assertSame(1, PendingRegistrationModel::query()->count());
    }

    public function test_deletesUnusedExpiredTokens(): void {
        $account = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'u@example.com', null);
        $this->token($account->id, now()->subHour());
        $this->token($account->id, now()->addHour());

        $this->prune();

        $this->assertSame(1, OneTimeTokenModel::query()->count());
    }

    // 使用済みは二重投入の検知に使うので、しばらく残す
    public function test_keepsRecentlyUsedTokens(): void {
        $account = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'u@example.com', null);
        $this->token($account->id, now()->subHour(), now()->subDay());

        $this->prune();

        $this->assertSame(1, OneTimeTokenModel::query()->count());
    }

    public function test_deletesOldUsedTokens(): void {
        $account = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'u@example.com', null);
        $this->token($account->id, now()->subMonth(), now()->subDays(30));

        $this->prune();

        $this->assertSame(0, OneTimeTokenModel::query()->count());
    }

    public function test_daysOptionChangesRetention(): void {
        $account = app(ChreeAccountRepository::class)->create(AccountOrigin::USER, 'u@example.com', null);
        $this->token($account->id, now()->subHour(), now()->subDays(3));

        $this->prune(1);

        $this->assertSame(0, OneTimeTokenModel::query()->count());
    }
}
