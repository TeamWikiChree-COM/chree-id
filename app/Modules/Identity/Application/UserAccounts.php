<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Infrastructure\UserAccountModel;

/**
 * UserAccount の有無を見る／作る。
 *
 * **「もう本人のものになっているか」の判定はここを通す。**
 * `origin` は出自の記録なので使わない。origin と統合状態は別の軸 (KAKUTEI.md)。
 */
class UserAccounts {
    /**
     * @param string $authIdentityId 認証主体のID (ULID)
     * @return bool 束ねる人格を持っているか
     */
    public function exists(string $authIdentityId): bool {
        return UserAccountModel::query()->where('auth_identity_id', $authIdentityId)->exists();
    }

    /**
     * 冪等。既にあれば何もしない。
     *
     * @param string $authIdentityId 認証主体のID (ULID)
     * @return void
     */
    public function ensure(string $authIdentityId): void {
        if ($this->exists($authIdentityId)) return;

        UserAccountModel::create(['auth_identity_id' => $authIdentityId]);
    }
}
