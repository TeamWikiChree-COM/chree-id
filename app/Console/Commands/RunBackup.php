<?php

namespace App\Console\Commands;

use App\Modules\Backup\Application\RunBackup as Backup;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * バックアップを取って Google Drive へ置く。
 *
 * 日次で cron から叩く。**本番では artisan を叩けないことがある**ので、
 * 同じ処理を管理画面 (/admin/maintenance) からも流せるようにしてある。
 */
class RunBackup extends Command {
    #[\Override]
    protected $signature = 'chreeid:backup';

    #[\Override]
    protected $description = 'データベースを暗号化してサーバ内と Google Drive に置く';

    /**
     * @param Backup $backup バックアップの本体
     * @return int
     */
    public function handle(Backup $backup): int {
        try {
            $result = $backup->execute();
        } catch (RuntimeException $e) {
            // 設定漏れも通信の失敗もここに来る。黙って 0 を返すと cron が成功に見える
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s を置きました (%d 表 / %d 行 / %d バイト)',
            $result->name,
            $result->tables,
            $result->rows,
            $result->bytes,
        ));

        if ($result->pruned !== []) {
            $this->info(count($result->pruned) . ' 件の古い控えを消しました');
        }

        return self::SUCCESS;
    }
}
