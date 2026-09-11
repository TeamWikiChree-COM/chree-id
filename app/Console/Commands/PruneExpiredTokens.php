<?php

namespace App\Console\Commands;

use App\Modules\Registry\Application\PruneTokens;
use Illuminate\Console\Command;

/**
 * 使い終わった使い捨てトークンと、確認されなかった登録申し込みを消す。
 *
 * 削除そのものは PruneTokens が持つ。ここは日次で叩くための入口で、
 * 管理画面からも同じものを呼ぶ。
 */
class PruneExpiredTokens extends Command {
    #[\Override]
    protected $signature = 'chreeid:prune-tokens {--days=7 : 使用済みトークンを残す日数}';

    #[\Override]
    protected $description = '期限切れの登録申し込みと使い捨てトークンを削除する';

    /**
     * @param PruneTokens $prune 掃除の本体
     * @return int
     */
    public function handle(PruneTokens $prune): int {
        $pruned = $prune->execute((int) $this->option('days'));

        $this->info(
            "登録申し込み {$pruned->registrations} 件、アドレス変更 {$pruned->emailChanges} 件、"
            . "未使用トークン {$pruned->expiredTokens} 件、使用済みトークン {$pruned->usedTokens} 件を削除しました"
        );

        return self::SUCCESS;
    }
}
