<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\AccountEmailModel;

/**
 * 統合で消える側のメールアドレスを、生き残る側の追加アドレスへ移す。
 *
 * 移さないと、寄せ元で使っていたアドレスを割り当てていたサービスが、
 * 統合した途端に主アドレスへ戻ってしまう。
 * 寄せ元の主アドレスも追加アドレスにする。ログインには使わせない (主アドレスは1つ)。
 */
class TransferAccountEmails {
    private readonly AuthIdentityRepository $accounts;

    public function __construct(AuthIdentityRepository $accounts) {
        $this->accounts = $accounts;
    }

    /**
     * トランザクションの中で呼ぶこと。
     *
     * @param string $sourceId 消える側のアカウントID (ULID)
     * @param string $targetId 生き残る側のアカウントID (ULID)
     * @return void
     */
    public function execute(string $sourceId, string $targetId): void {
        $source = $this->accounts->findById($sourceId);
        if ($source?->email !== null) $this->keep($targetId, $source->email, $source->emailVerifiedAt);

        $rows = AccountEmailModel::query()->where('auth_identity_id', $sourceId)->get();
        foreach ($rows as $row) {
            $this->keep($targetId, $row->email, $row->verified_at);
            $row->delete();
        }
    }

    /**
     * 寄せ先に同じアドレスがあれば、確認済みの状態だけ引き継ぐ。
     *
     * @param string $targetId 生き残る側のアカウントID (ULID)
     * @param string $email アドレス
     * @param \Carbon\CarbonInterface|null $verifiedAt 確認済み日時
     * @return void
     */
    private function keep(string $targetId, string $email, ?\Carbon\CarbonInterface $verifiedAt): void {
        $primary = $this->accounts->findById($targetId)?->email;
        if ($primary !== null && strcasecmp($primary, $email) === 0) return;

        $existing = AccountEmailModel::query()
            ->where('auth_identity_id', $targetId)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if ($existing === null) {
            AccountEmailModel::create(['auth_identity_id' => $targetId, 'email' => $email, 'verified_at' => $verifiedAt]);

            return;
        }

        if (!$existing->isVerified() && $verifiedAt !== null) $existing->forceFill(['verified_at' => $verifiedAt])->save();
    }
}
