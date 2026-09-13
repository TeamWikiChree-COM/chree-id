<?php
namespace App\Modules\Backup\Infrastructure;

use App\Modules\Backup\Domain\BackupSettings;
use RuntimeException;

/**
 * サーバ内の置き場。
 *
 * Drive だけだと、トークンが失効したり通信が落ちたりした日に控えが1つも無くなる。
 * 手元にも同じ暗号化済みの控えを持っておく。サーバごと失ったときのために Drive は残す。
 */
class LocalBackupStore {
    /** 自分が置いたものだけを数えて消す。同じ場所に人が置いたファイルを巻き込まない */
    private const PATTERN = '/^chreeid-\d{8}-\d{6}\.json\.enc$/';

    private readonly BackupSettings $settings;

    public function __construct(BackupSettings $settings) {
        $this->settings = $settings;
    }

    /**
     * @return bool サーバ内にも置くか
     */
    public function isEnabled(): bool {
        return $this->settings->localEnabled();
    }

    /**
     * @param string $name 置くファイル名
     * @param string $content 中身 (暗号化済み)
     * @return void
     * @throws RuntimeException 書けなかった
     */
    public function put(string $name, string $content): void {
        $dir = $this->settings->localPath();

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("バックアップの置き場を作れませんでした: {$dir}");
        }

        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $content, LOCK_EX) === false) {
            throw new RuntimeException("バックアップを書き込めませんでした: {$dir}");
        }
    }

    /**
     * 置いてあるものを新しい順に返す。
     *
     * @return list<array{id: string, name: string, createdTime: string}>
     */
    public function list(): array {
        $dir = $this->settings->localPath();
        if (!is_dir($dir)) return [];

        $files = [];
        foreach (scandir($dir) ?: [] as $name) {
            if (preg_match(self::PATTERN, $name) !== 1) continue;

            $mtime = (int) filemtime($dir . DIRECTORY_SEPARATOR . $name);
            $files[] = ['id' => $name, 'name' => $name, 'createdTime' => date(DATE_ATOM, $mtime)];
        }

        // 名前に日時が入っているので、名前の降順がそのまま新しい順になる
        usort($files, fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $files;
    }

    /**
     * 残す日数を過ぎたものを消す。
     *
     * @return list<string> 消した控えの名前
     * @throws RuntimeException 消せなかった
     */
    public function prune(): array {
        $limit = now()->subDays($this->settings->localDays())->getTimestamp();
        $dir = $this->settings->localPath();
        $pruned = [];

        foreach ($this->list() as $file) {
            if (strtotime($file['createdTime']) >= $limit) continue;

            if (!@unlink($dir . DIRECTORY_SEPARATOR . $file['name'])) {
                throw new RuntimeException("古いバックアップを消せませんでした: {$file['name']}");
            }
            $pruned[] = $file['name'];
        }

        return $pruned;
    }
}
