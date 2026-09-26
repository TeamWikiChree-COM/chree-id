<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Infrastructure\PendingEmailChangeModel;

/**
 * 確認待ちのメールアドレス変更。
 */
class PendingEmailChanges {
    /**
     * 出さないと、送ったきり届かなかったときに本人が何も分からない。
     *
     * @param string $accountId アカウントID (ULID)
     * @return array{email: string, expiresAt: string}|null 申し込みが無ければ null
     */
    public function current(string $accountId): ?array {
        $pending = PendingEmailChangeModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        if ($pending === null) return null;

        return ['email' => $pending->new_email, 'expiresAt' => $pending->expires_at->toDateTimeString()];
    }

    /**
     * 打ち間違えたまま期限切れを待たせない。届いたリンクもこれで無効になる。
     *
     * @param string $accountId アカウントID (ULID)
     * @return void
     */
    public function cancel(string $accountId): void {
        PendingEmailChangeModel::query()->where('auth_identity_id', $accountId)->delete();
    }
}
