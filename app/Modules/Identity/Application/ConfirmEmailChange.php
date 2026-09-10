<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\PendingEmailChangeModel;
use Illuminate\Support\Facades\DB;

/**
 * 確認リンクを受けてメールアドレスを差し替える。
 *
 * 差し替えと同時に検証済みにする。このリンクが開けた時点で到達性は確かめられている。
 */
class ConfirmEmailChange {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly ResolveByEmail $byEmail,
    ) {}

    /**
     * @param string $token メールに載せた平文トークン
     * @return string|null 差し替えたアカウントID。使えないリンクなら null
     */
    public function execute(string $token): ?string {
        $pending = PendingEmailChangeModel::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($pending === null || !$pending->isUsable()) return null;

        // 申し込みから確認までの間に、そのアドレスが他のアカウントに使われている可能性がある
        $existing = $this->byEmail->primary($pending->new_email);
        if ($existing !== null && $existing->id !== $pending->auth_identity_id) {
            $pending->delete();

            return null;
        }

        return DB::transaction(function () use ($pending): string {
            $this->accounts->updateEmail($pending->auth_identity_id, $pending->new_email);
            $this->accounts->markEmailVerified($pending->auth_identity_id);
            $pending->delete();

            return $pending->auth_identity_id;
        });
    }
}
