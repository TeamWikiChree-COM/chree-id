<?php
namespace App\Modules\Linking\Application;

use App\Modules\Provider\Infrastructure\AccessTokenModel;

/**
 * サービスへの連携を解除する。
 *
 * 発行済みのアクセストークンを失効させるだけで、service_subject_ids は消さない。
 * sub はサービスごとに採番していて、消して作り直すと値が変わる。
 * 向こうから見ると別人になり、繋ぎ直しても元のアカウントに戻れなくなる。
 */
class RevokeServiceAccess {
    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $clientId サービスの client_id
     * @return int 失効させたトークンの数
     */
    public function execute(string $accountId, string $clientId): int {
        return AccessTokenModel::query()
            ->where('chree_account_id', $accountId)
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
