<?php
namespace App\Modules\Provider\Application;

use App\Modules\Provider\Infrastructure\AccessTokenModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * 発行済みのアクセストークンを失効させる。
 *
 * 失効の条件が呼び出し元ごとに食い違うと、切ったはずのトークンが生き残るので、ここに集める。
 */
class RevokeAccessTokens {
    /**
     * @param string $accountId 認証主体のID (ULID)
     * @return int 失効させた数
     */
    public function forAccount(string $accountId): int {
        return $this->revoke(AccessTokenModel::query()->where('auth_identity_id', $accountId));
    }

    /**
     * @param string $accountId 認証主体のID (ULID)
     * @param string $clientId サービスの client_id
     * @return int 失効させた数
     */
    public function forClient(string $accountId, string $clientId): int {
        return $this->revoke(AccessTokenModel::query()->where('auth_identity_id', $accountId)->where('client_id', $clientId));
    }

    /**
     * @param string $serviceAccountId サービスアカウントのID (ULID)
     * @return int 失効させた数
     */
    public function forServiceAccount(string $serviceAccountId): int {
        return $this->revoke(AccessTokenModel::query()->where('service_account_id', $serviceAccountId));
    }

    /**
     * @param Builder<AccessTokenModel> $query 対象を絞ったクエリ
     * @return int 失効させた数
     */
    private function revoke(Builder $query): int {
        return $query->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
