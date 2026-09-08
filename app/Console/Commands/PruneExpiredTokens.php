<?php

namespace App\Console\Commands;

use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Infrastructure\PendingRegistrationModel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * 使い終わった使い捨てトークンと、確認されなかった登録申し込みを消す。
 *
 * pending_registrations にはパスワードハッシュが入っているので、
 * 期限が切れたものを残しておく理由が無い。
 */
class PruneExpiredTokens extends Command {
    #[\Override]
    protected $signature = 'chreeid:prune-tokens {--days=7 : 使用済みトークンを残す日数}';

    #[\Override]
    protected $description = '期限切れの登録申し込みと使い捨てトークンを削除する';

    /**
     * @return int
     */
    public function handle(): int {
        $registrations = $this->prune(
            PendingRegistrationModel::query()->where('expires_at', '<', now()),
        );

        $expired = $this->prune(
            OneTimeTokenModel::query()->whereNull('used_at')->where('expires_at', '<', now()),
        );

        // 使用済みは二重投入の検知に使うので、すぐには消さず少し残す
        $used = $this->prune(
            OneTimeTokenModel::query()
                ->whereNotNull('used_at')
                ->where('used_at', '<', now()->subDays($this->days())),
        );

        $this->info("登録申し込み {$registrations} 件、未使用トークン {$expired} 件、使用済みトークン {$used} 件を削除しました");

        return self::SUCCESS;
    }

    /**
     * @param Builder<covariant \Illuminate\Database\Eloquent\Model> $query 消す対象
     * @return int 消した件数
     */
    private function prune(Builder $query): int {
        $deleted = $query->delete();

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * @return int 使用済みトークンを残す日数
     */
    private function days(): int {
        $days = (int) $this->option('days');

        return $days > 0 ? $days : 7;
    }
}
