<?php
namespace App\Modules\Linking\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\UserAccountModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Facades\DB;

/**
 * 移行元でアカウントが消えたときに、対応するサービスアカウントを畳む。
 *
 * **消えたのはサービス上の人格であって、認証主体ではない** (KAKUTEI.md 退会)。
 * 一律に認証主体を停止すると、既に移行を済ませて他サービスでも使っている人が
 * どこからも入れなくなる。何が残っているかを見てから決める。
 *
 * | 残っているもの | すること |
 * | --- | --- |
 * | UserAccount / 他の ServiceAccount | その ServiceAccount の行だけ消す |
 * | 何も無い | 認証主体ごと消す |
 */
class DeactivateServiceAccount {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly RevokeServiceAccess $revoke,
    ) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return bool 対象が見つかり畳めたか
     */
    public function execute(OAuthClientModel $client, string $serviceUserId): bool {
        $link = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        if ($link === null) return false;

        DB::transaction(function () use ($client, $link): void {
            // service_account_id は nullOnDelete なので、先に畳まないと
            // 発行済みのトークンが認証主体だけを指したまま生き残る
            $this->revoke->execute($link->auth_identity_id, $client->id);

            $identityId = $link->auth_identity_id;
            $link->delete();

            if ($this->stillInUse($identityId)) return;

            $this->accounts->delete($identityId);
        });

        return true;
    }

    /**
     * その認証主体を残す理由があるか。
     *
     * @param string $identityId アカウントID (ULID)
     * @return bool 残すべきなら true
     */
    private function stillInUse(string $identityId): bool {
        $bound = UserAccountModel::query()->where('auth_identity_id', $identityId)->exists();
        if ($bound) return true;

        return ServiceAccountModel::query()->where('auth_identity_id', $identityId)->exists();
    }
}
