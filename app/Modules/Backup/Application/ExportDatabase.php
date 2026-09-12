<?php
namespace App\Modules\Backup\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * データベースの中身を1つの配列に集める。
 *
 * **`mysqldump` / `pg_dump` を使わない。** 共用サーバでは外部コマンドを叩けるとは
 * 限らず、本番 (PostgreSQL) とローカル (SQLite) で別の道具になってしまう。
 * 接続から表を引いて SELECT するだけなら、どの driver でも同じものが出る。
 */
class ExportDatabase {
    /** この形式の版。読み戻す側が形の違いに気付けるようにする */
    public const VERSION = 1;

    /**
     * 入れない表。
     *
     * セッションと待ち行列は、戻したところで意味を持たない一時的なもの。
     * **セッションは戻せば他人のログイン状態が蘇る**ので、むしろ入れてはいけない。
     */
    private const SKIP = [
        'migrations', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
    ];

    /**
     * @return array{version: int, takenAt: string, connection: string, tables: array<string, list<array<string, mixed>>>}
     */
    public function execute(): array {
        $tables = [];

        foreach ($this->tables() as $table) {
            $rows = [];

            foreach (DB::table($table)->get() as $row) {
                /** @var array<string, mixed> $columns */
                $columns = (array) $row;
                $rows[] = $columns;
            }

            $tables[$table] = $rows;
        }

        return [
            'version' => self::VERSION,
            'takenAt' => now()->toIso8601String(),
            // 戻す先を間違えないよう、どこから取ったかを残す
            'connection' => $this->connection(),
            'tables' => $tables,
        ];
    }

    /**
     * @return list<string> 取る表の名前
     */
    private function tables(): array {
        $names = [];

        foreach (Schema::getTables() as $table) {
            $name = is_array($table) ? ($table['name'] ?? null) : null;

            if (is_string($name) && !in_array($name, self::SKIP, true)) $names[] = $name;
        }

        sort($names);

        return $names;
    }

    /**
     * @return string いまの接続の名前
     */
    private function connection(): string {
        $name = config('database.default');

        return is_string($name) ? $name : 'unknown';
    }
}
