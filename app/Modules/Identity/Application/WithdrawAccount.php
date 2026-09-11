<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use Illuminate\Support\Facades\DB;

/**
 * 本人の申し出で ChreeID を退会する。
 *
 * **すぐには消さない。** 押し間違いや乗っ取りから戻せるように、まず退会済みとして
 * 印を付け、猶予を過ぎてから `PurgeDeletedAccounts` が行ごと消す。
 *
 * 印を付けた時点でログインは通らなくなる (`softDelete` が `suspended_at` も立てる)。
 * 連携先から見ても即座に使えなくなるよう、発行済みのトークンはここで失効させる。
 */
class WithdrawAccount {
    public function __construct(private readonly AuthIdentityRepository $accounts) {}

    /**
     * @param string $accountId アカウントID (ULID)
     * @return bool 退会させたか。見つからない・既に退会済みなら false
     */
    public function execute(string $accountId): bool {
        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->isDeleted()) return false;

        DB::transaction(function () use ($accountId): void {
            // 猶予のあいだ行は残るので、失効させないとトークンが生き続ける
            AccessTokenModel::query()
                ->where('auth_identity_id', $accountId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $this->accounts->softDelete($accountId);
        });

        return true;
    }
}
