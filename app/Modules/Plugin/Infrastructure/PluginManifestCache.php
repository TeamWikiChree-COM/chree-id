<?php
namespace App\Modules\Plugin\Infrastructure;

use App\Modules\Plugin\Domain\PluginManifest;

/**
 * plugins/ を探して plugin.json を読んだ結果の控え。
 *
 * 探して読むのは毎リクエストかかり、プラグインが増えるほど重くなる (手元の計測で1フォルダあたり 0.15ms ほど)。
 * 控えは PHP の配列として書くので、OPcache に載って読み込みがほぼ無くなる。
 *
 * 古くなったかは、plugins/ の中のフォルダ名の一覧と、各 plugin.json の更新時刻で見る。plugin.json の中身は読まない。
 * フォルダの増減を plugins/ 自身の更新時刻で見ないのは、ファイルシステムによって (exFAT など) 変わらないため。
 * 時刻は秒単位なので、同じ秒のうちの書き換えは見逃しうる。本体が書き換えるとき (PluginSwitch) は明示的に消す。
 */
class PluginManifestCache {
    private readonly string $file;

    /**
     * @param string $file 控えを書くファイル (bootstrap/cache/plugins.php など)
     */
    public function __construct(string $file) {
        $this->file = $file;
    }

    /**
     * @param string $root plugins/ のパス
     * @return list<PluginManifest>|null 使える控えが無ければ null
     */
    public function read(string $root): ?array {
        if (!is_file($this->file)) return null;

        $cached = require $this->file;
        if (!is_array($cached) || ($cached['root'] ?? null) !== $root || !$this->isFresh($cached)) return null;

        return array_map(static fn (array $m): PluginManifest => new PluginManifest(
            $m['name'],
            $m['version'],
            $m['provider'],
            $m['enabled'],
            $m['title'],
            $m['description'],
        ), $cached['manifests']);
    }

    /**
     * @param string $root
     * @param list<PluginManifest> $manifests
     * @param list<string> $files 読んだ plugin.json のパス
     */
    public function write(string $root, array $manifests, array $files): void {
        $mtimes = [];
        foreach ($files as $file) $mtimes[$file] = (int) filemtime($file);

        $data = [
            'root' => $root,
            'dirs' => $this->dirs($root),
            'files' => $mtimes,
            'manifests' => array_map(static fn (PluginManifest $m): array => [
                'name' => $m->name,
                'version' => $m->version,
                'provider' => $m->provider,
                'enabled' => $m->enabled,
                'title' => $m->title,
                'description' => $m->description,
            ], $manifests),
        ];

        // 書きかけを読まれないよう、別名で書いてから置き換える
        $tmp = $this->file . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, '<?php return ' . var_export($data, true) . ";\n") === false) return;
        if (!@rename($tmp, $this->file)) @unlink($tmp);

        // OPcache が古い中身を持ち続けないように
        if (function_exists('opcache_invalidate')) @opcache_invalidate($this->file, true);
    }

    /**
     * 控えを消す。次のリクエストで作り直される。
     */
    public function clear(): void {
        if (is_file($this->file)) @unlink($this->file);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($this->file, true);
    }

    /**
     * @param string $root
     * @return list<string> plugins/ の中の名前。並びは scandir の既定 (名前順)
     */
    private function dirs(string $root): array {
        $names = @scandir($root);
        if ($names === false) return [];

        return array_values(array_diff($names, ['.', '..']));
    }

    /**
     * @param array<string, mixed> $cached
     * @return bool
     */
    private function isFresh(array $cached): bool {
        if ($this->dirs($cached['root']) !== ($cached['dirs'] ?? null)) return false;

        foreach ($cached['files'] as $file => $mtime) {
            if (@filemtime($file) !== $mtime) return false;
        }

        return true;
    }
}
