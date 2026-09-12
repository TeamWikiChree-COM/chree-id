<?php
namespace App\Support\Lang;

/**
 * JSON のフラットなキーを、Laravel の PHP ローダーが読める形に落とす。
 *
 * ARCHITECTURE.md 10.4。`__('account.login.title')` は
 * 「account.php の login.title」を探すので、**第1セグメントでファイルを分け、
 * 残りをネストさせる**。こうすれば独自ローダーを書かずに OPcache の利点だけ得られる。
 */
final class LangCompiler {
    /**
     * @param array<string, array<string, string>> $locales ロケール => キー => 文言
     * @param string $outDir generated/lang のパス
     * @return int 書き出したファイル数
     * @throws LangBuildException 書き出せない
     */
    public function compile(array $locales, string $outDir): int {
        $written = 0;

        foreach ($locales as $locale => $messages) {
            $dir = rtrim($outDir, '/') . '/' . $this->shorten($locale);
            $this->prepare($dir);

            foreach ($this->split($messages) as $file => $tree) {
                $this->write($dir . '/' . $file . '.php', $locale, $tree);
                $written++;
            }
        }

        return $written;
    }

    /**
     * `ja_jp` を Laravel が使う `ja` にする。正の側は地域まで持たせておきたいが、
     * 実行時のロケールは2文字で回っているため。
     *
     * @param string $locale JSON のファイル名
     * @return string
     */
    private function shorten(string $locale): string {
        $head = strstr($locale, '_', true);

        return $head === false ? $locale : $head;
    }

    /**
     * @param array<string, string> $messages キー => 文言
     * @return array<string, array<string, mixed>> ファイル名 => ネストした配列
     */
    private function split(array $messages): array {
        $files = [];

        foreach ($messages as $key => $text) {
            $segments = explode('.', $key);
            $file = array_shift($segments);

            // LangValidator が落としているはずだが、ここだけ見ても成り立つようにする
            if ($segments === []) throw new LangBuildException("ドットの無いキーは分割できません: {$key}");

            $files[$file] ??= [];
            $this->nest($files[$file], $segments, $text);
        }

        ksort($files);

        return $files;
    }

    /**
     * @param array<string, mixed> $tree 書き込み先
     * @param non-empty-list<string> $segments 残りのセグメント
     * @param string $text 文言
     * @return void
     */
    private function nest(array &$tree, array $segments, string $text): void {
        $last = array_pop($segments);
        $cursor = &$tree;

        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) $cursor[$segment] = [];
            $cursor = &$cursor[$segment];
        }

        $cursor[$last] = $text;
    }

    /**
     * 文字列を自前で連結すると `'I'm here'` で壊れる。エスケープは var_export() に任せる。
     *
     * @param string $path 出力先
     * @param string $locale 正のファイル名
     * @param array<string, mixed> $tree 書き出す配列
     * @return void
     * @throws LangBuildException
     */
    private function write(string $path, string $locale, array $tree): void {
        $body = "<?php\n\n// 自動生成。編集しないこと。正は lang/{$locale}.json\nreturn "
            . var_export($tree, true) . ";\n";

        if (file_put_contents($path, $body) === false) {
            throw new LangBuildException("書き出せませんでした: {$path}");
        }
    }

    /**
     * @param string $dir 出力先ディレクトリ
     * @return void
     * @throws LangBuildException
     */
    private function prepare(string $dir): void {
        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new LangBuildException("ディレクトリを作れませんでした: {$dir}");
        }

        // 正から消えたキーのファイルを残さない
        foreach (glob($dir . '/*.php') ?: [] as $stale) {
            unlink($stale);
        }
    }
}
