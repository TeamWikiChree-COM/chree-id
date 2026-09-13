<?php
namespace App\Modules\Backup\Application;

use App\Modules\Backup\Domain\BackupResult;
use App\Modules\Backup\Domain\BackupSettings;
use App\Modules\Backup\Infrastructure\GoogleDrive;
use App\Modules\Backup\Infrastructure\LocalBackupStore;
use RuntimeException;

/**
 * 取って、包んで、置いて、古いものを消す。
 *
 * cron からも管理画面からも同じここを呼ぶ。**数える条件と消す条件を分けない**ため、
 * 世代の判定は各置き場の prune() に一本化してある。
 */
class RunBackup {
    public function __construct(
        private readonly ExportDatabase $export,
        private readonly BackupCipher $cipher,
        private readonly GoogleDrive $drive,
        private readonly LocalBackupStore $local,
        private readonly BackupSettings $settings,
    ) {}

    /**
     * @return BackupResult
     * @throws RuntimeException 設定が足りない、または置けなかった
     */
    public function execute(): BackupResult {
        // 鍵が無いなら取らない。暗号化できない控えを外へ出すくらいなら、無いほうがよい
        if (!$this->cipher->isConfigured()) throw new RuntimeException('CHREEID_BACKUP_KEY が設定されていません');
        if (!$this->local->isEnabled() && !$this->drive->isConfigured()) {
            throw new RuntimeException('バックアップの置き場がありません (サーバ内も Google Drive も無効です)');
        }

        $data = $this->export->execute();
        $sealed = $this->cipher->seal($data);
        $name = sprintf('chreeid-%s.json.enc', now()->format('Ymd-His'));
        $pruned = [];

        // 先にサーバ内へ置く。Drive が落ちた日にも控えが残るように
        if ($this->local->isEnabled()) {
            $this->local->put($name, $sealed);
            $pruned = $this->local->prune();
        }

        if ($this->drive->isConfigured()) {
            $this->drive->upload($name, $sealed);
            $pruned = [...$pruned, ...$this->prune()];
        }

        return new BackupResult(
            $name,
            strlen($sealed),
            count($data['tables']),
            array_sum(array_map('count', $data['tables'])),
            $pruned,
        );
    }

    /**
     * Drive 上で残す世代を超えた分を消す。
     *
     * **置いたあとに消す。** 先に消すと、置くのに失敗したときに世代が1つ減る。
     *
     * @return list<string> 消した控えの名前
     */
    public function prune(): array {
        $keep = $this->settings->keep();

        // 一覧は新しい順。ここで数えたものがそのまま消す対象になる
        $old = array_slice($this->drive->list(), $keep);
        $pruned = [];

        foreach ($old as $file) {
            $this->drive->delete($file['id']);
            $pruned[] = $file['name'];
        }

        return $pruned;
    }
}
