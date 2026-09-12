<?php
namespace App\Modules\Registry\Infrastructure;

use Illuminate\Support\Facades\File;

/**
 * `storage/logs` の中身を読む。
 *
 * **末尾だけ読む。** ログは際限なく伸びるので、全部読むと本番で落ちる。
 * 画面に出すのは直近の分だけでよく、それ以上要るならファイルを落として見る。
 */
class LogFile {
    /** 末尾から読む量。数百KBあれば直近の数百件は入る */
    private const TAIL_BYTES = 262144;

    /** 画面に出す最大件数 */
    private const MAX_ENTRIES = 200;

    /** 1件の始まりを見分ける。例: [2026-09-12 00:20:32] local.ERROR: 本文 */
    private const HEAD = '/^\[(\d{4}-\d{2}-\d{2}[T ][\d:.]+)[^\]]*\]\s+(\S+?)\.([A-Z]+):\s?(.*)$/';

    /**
     * 読めるログファイル。新しい順。
     *
     * @return list<string> ファイル名だけ (パスは外に出さない)
     */
    public function available(): array {
        $paths = array_values(array_filter(File::glob(storage_path('logs/*.log')), is_string(...)));

        usort($paths, fn (string $a, string $b): int => File::lastModified($b) <=> File::lastModified($a));

        $names = [];
        foreach ($paths as $path) {
            $names[] = basename($path);
        }

        return $names;
    }

    /**
     * 末尾から順に読む。
     *
     * @param string $name ファイル名。available() に無いものは読まない
     * @return list<array{at: string, channel: string, level: string, message: string, trace: string}> 新しい順
     */
    public function tail(string $name): array {
        $path = $this->pathOf($name);
        if ($path === null) return [];

        $entries = $this->parse($this->readTail($path));

        return array_slice(array_reverse($entries), 0, self::MAX_ENTRIES);
    }

    /**
     * 中身を空にする。ファイル自体は残す (消すと権限が変わって書けなくなる環境がある)。
     *
     * @param string $name ファイル名
     * @return bool 空にできたか
     */
    public function clear(string $name): bool {
        $path = $this->pathOf($name);
        if ($path === null) return false;

        return File::put($path, '') !== false;
    }

    /**
     * **名前は必ず available() と突き合わせる。** 画面から来た文字列をそのまま
     * パスに繋ぐと、`../` で storage の外を読まれる。
     *
     * @param string $name ファイル名
     * @return string|null 読んでよい実体のパス。無ければ null
     */
    private function pathOf(string $name): ?string {
        if (!in_array($name, $this->available(), true)) return null;

        return storage_path("logs/{$name}");
    }

    /**
     * @param string $path 実体のパス
     * @return string 末尾の中身
     */
    private function readTail(string $path): string {
        $size = File::size($path);
        if ($size <= self::TAIL_BYTES) return (string) File::get($path);

        $handle = fopen($path, 'rb');
        if ($handle === false) return '';

        fseek($handle, -self::TAIL_BYTES, SEEK_END);
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        // 先頭は途中で切れている。行の途中から始まる分は捨てる
        $newline = strpos($tail, "\n");

        return $newline === false ? '' : substr($tail, $newline + 1);
    }

    /**
     * 1件ずつに切る。見出しに続く行 (スタックトレース) は同じ件にまとめる。
     *
     * @param string $contents ログの中身
     * @return list<array{at: string, channel: string, level: string, message: string, trace: string}> 古い順
     */
    private function parse(string $contents): array {
        $entries = [];
        $current = null;
        $trace = [];

        foreach (explode("\n", $contents) as $line) {
            if (preg_match(self::HEAD, $line, $found) !== 1) {
                // 見出しより前に落ちている行は、切れた件の残骸なので捨てる
                if ($current !== null) $trace[] = $line;

                continue;
            }

            if ($current !== null) $entries[] = $this->close($current, $trace);

            $current = [
                'at' => $found[1],
                'channel' => $found[2],
                'level' => $found[3],
                'message' => $found[4],
                'trace' => '',
            ];
            $trace = [];
        }

        if ($current !== null) $entries[] = $this->close($current, $trace);

        return $entries;
    }

    /**
     * 溜めておいた続きの行を、その件にくっつけて閉じる。
     *
     * @param array{at: string, channel: string, level: string, message: string, trace: string} $entry
     * @param list<string> $trace
     * @return array{at: string, channel: string, level: string, message: string, trace: string}
     */
    private function close(array $entry, array $trace): array {
        $entry['trace'] = trim(implode("\n", $trace));

        return $entry;
    }
}
