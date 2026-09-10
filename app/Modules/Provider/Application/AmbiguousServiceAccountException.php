<?php
namespace App\Modules\Provider\Application;

use RuntimeException;

/**
 * 1人が同じサービスに複数のサービスアカウントを持っていて、どれとして入るか決まらない。
 *
 * 統合するとこの状態になりうる (WikiChree は1アカウント1Wiki なので普通に起きる)。
 * 解くには、同意画面などでサービスアカウントを選ばせる必要がある。
 * 黙ってどれかを返すと、別のアカウントとしてログインさせてしまう。
 */
class AmbiguousServiceAccountException extends RuntimeException {
    /**
     * @param string $clientId 接続先サービスの client_id
     * @param string $accountId アカウントID (ULID)
     */
    public function __construct(public readonly string $clientId, public readonly string $accountId) {
        parent::__construct("複数のサービスアカウントがあり、渡す sub を決められません: {$clientId}");
    }
}
