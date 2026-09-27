<?php
namespace App\Modules\Plugin\Infrastructure;

use App\Modules\Plugin\Domain\PluginManifest;
use App\Support\Registry\DynamicRegistry;
use RuntimeException;
use Override;

/**
 * plugins/ 以下から plugin.json を持つディレクトリを探して束ねる。
 *
 * @extends DynamicRegistry<PluginManifest>
 */
class PluginRegistry extends DynamicRegistry {
    private readonly string $root;
    private readonly ?PluginManifestCache $cache;

    /**
     * @param string $root plugins/ のパス
     * @param PluginManifestCache|null $cache 探した結果の控え。テストで別の置き場を読むときは渡さない
     * @throws RuntimeException plugin.json が読めない、壊れている場合
     */
    public function __construct(string $root, ?PluginManifestCache $cache = null) {
        $this->root = $root;
        $this->cache = $cache;
        $this->load();
    }

    /**
     * 控えを消す。plugin.json を書き換えたあとに呼ぶ。
     */
    public function forgetCache(): void {
        $this->cache?->clear();
    }

    /**
     * @return string plugins/ のパス
     */
    public function root(): string {
        return $this->root;
    }

    /**
     * @return list<PluginManifest> 名前順
     */
    public function all(): array {
        return array_values($this->items());
    }

    /**
     * @return list<PluginManifest> plugin.json で有効になっているもの。名前順
     */
    public function enabled(): array {
        return array_values(array_filter($this->all(), static fn (PluginManifest $plugin): bool => $plugin->enabled));
    }

    /**
     * @param PluginManifest $plugin
     * @param string $file プラグインのフォルダからの相対パス (例: routes/web.php)
     * @return string
     */
    public function path(PluginManifest $plugin, string $file): string {
        return "{$this->root}/{$plugin->name}/{$file}";
    }

    /**
     * @param string $key ディレクトリ名
     * @return PluginManifest|null
     */
    public function find(string $key): ?PluginManifest {
        return parent::find($key);
    }

    /**
     * @return list<PluginManifest> 名前順
     * @throws RuntimeException plugin.json が読めない・壊れている場合
     */
    #[Override]
    protected function discover(): iterable {
        $cached = $this->cache?->read($this->root);
        if ($cached !== null) return $cached;

        $files = glob($this->root . '/*/plugin.json') ?: [];
        sort($files);

        $manifests = array_map(fn (string $file): PluginManifest => $this->read($file), $files);
        $this->cache?->write($this->root, $manifests, $files);

        return $manifests;
    }

    /**
     * @param PluginManifest $item
     * @return string ディレクトリ名
     */
    #[Override]
    protected function keyOf(object $item): string {
        return $item->name;
    }

    /**
     * @param string $file plugin.json のパス
     * @return PluginManifest
     * @throws RuntimeException
     */
    private function read(string $file): PluginManifest {
        $json = json_decode((string) file_get_contents($file), true);

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
