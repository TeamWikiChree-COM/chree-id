<?php
namespace App\Modules\Backup\Domain;

/**
 * 1回のバックアップの結果。呼び出し元が人に見せるために使う。
 */
final class BackupResult {
    /**
     * @param string $name 置いたファイル名
     * @param int $bytes 置いた大きさ
     * @param int $tables 入れた表の数
     * @param int $rows 入れた行の数
     * @param list<string> $pruned 消した古い控えの名前
     */
    public function __construct(
        public readonly string $name,
        public readonly int $bytes,
        public readonly int $tables,
        public readonly int $rows,
        public readonly array $pruned,
    ) {}
}
