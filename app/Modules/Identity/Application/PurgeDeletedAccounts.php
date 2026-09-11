<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;

/**
 * 猶予を過ぎた退会済みアカウントを消す。
 *
 * 退会は行に印を付けるだけで、実際に消えるのはここ。
 * credentials / service_accounts / user_accounts は FK の cascade で一緒に消える。
 *
 * **戻せるのは猶予のあいだだけ。** ここを通ったあとは復旧できない。
 */
class PurgeDeletedAccounts {
    /** 設定が壊れていたときに使う猶予日数 */
    private const FALLBACK_DAYS = 7;

    public function __construct(private readonly AuthIdentityRepository $accounts) {}

    /**
     * @param int|null $days 退会から何日残すか。省略すると設定値
     * @return int 消した件数
     */
    public function execute(?int $days = null): int {
        return $this->accounts->purgeDeletedBefore($days ?? self::graceDays());
    }

    /**
     * 消さずに、消える件数だけを数える。
     *
     * @param int|null $days 退会から何日残すか。省略すると設定値
     * @return int
     */
    public function due(?int $days = null): int {
        return $this->accounts->countDeletedBefore($days ?? self::graceDays());
    }

    /**
     * @return int 猶予日数
     */
    public static function graceDays(): int {
        $days = config('chreeid.account_purge_days');

        return is_int($days) && $days > 0 ? $days : self::FALLBACK_DAYS;
    }
}
