<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\UserAccountModel;

/**
 * UserAccount の有無を見る／作る。
 *
 * **「もう本人のものになっているか」の判定はここを通す。**
 * 昇格もここを通すので、origin (種別) を実体と揃えるのもここの役目。
 */
class UserAccounts {
    private readonly AuthIdentityRepository $accounts;

    public function __construct(AuthIdentityRepository $accounts) {
        $this->accounts = $accounts;
    }

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
     */
    public function ensure(string $authIdentityId): void {
        // 引き取り・統合で昇格したのに service のまま残ると、種別が実体と食い違う
        $this->accounts->changeOrigin($authIdentityId, AccountOrigin::USER);

        if ($this->exists($authIdentityId)) return;

        UserAccountModel::create(['auth_identity_id' => $authIdentityId]);
    }
}
