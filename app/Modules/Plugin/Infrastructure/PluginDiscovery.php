<?php
namespace App\Modules\Plugin\Infrastructure;

use App\Modules\Plugin\Domain\PluginManifest;
use RuntimeException;

/**
 * plugins/ 以下から plugin.json を持つディレクトリを探す。
 *
 * 置くだけで読み込まれる形にしている。本体側の一覧へ書き足す手順があると、
 * プラグインを足すたびに本体へ差分が出てしまう。
 */
class PluginDiscovery {
    private readonly string $root;

    /**
     * @param string $root plugins/ のパス
     */
    public function __construct(string $root) {
        $this->root = $root;
    }

    /**
     * @return string plugins/ のパス
     */
    public function root(): string {
        return $this->root;
    }

    /**
     * @return list<PluginManifest> 名前順
     * @throws RuntimeException plugin.json が読めない・壊れている場合
     */
    public function all(): array {
        $files = glob($this->root . '/*/plugin.json') ?: [];
        sort($files);

        return array_map(fn (string $file): PluginManifest => $this->read($file), $files);
    }

    /**
     * @param string $name ディレクトリ名
     * @return PluginManifest|null
     */
    public function find(string $name): ?PluginManifest {
        $file = "{$this->root}/{$name}/plugin.json";

        return is_file($file) ? $this->read($file) : null;
    }

    /**
     * @param string $file plugin.json のパス
     * @return PluginManifest
     * @throws RuntimeException
     */
    private function read(string $file): PluginManifest {
        $json = json_decode((string) file_get_contents($file), true);

        // 黙って飛ばすと「置いたのに出てこない」の原因が分からない
        if (!is_array($json) || !is_string($json['provider'] ?? null)) {
            throw new RuntimeException("plugin.json が読めません: {$file}");
        }

        return new PluginManifest(
            basename(dirname($file)),
            is_string($json['version'] ?? null) ? $json['version'] : '0.0.0',
            $json['provider'],
            ($json['enabled'] ?? true) === true,
            $this->localized($json['title'] ?? null),
            $this->localized($json['description'] ?? null),
        );
    }

    /**
     * 手書きの JSON なので、文字列でない値が混ざっていても落とさずに捨てる。
     *
     * @param mixed $value ロケールをキーにした文言
     * @return array<string, string>
     */
    private function localized(mixed $value): array {
        if (!is_array($value)) return [];

        $result = [];
        foreach ($value as $locale => $text) {
            if (is_string($locale) && is_string($text)) $result[$locale] = $text;
        }

        return $result;
    }
}
