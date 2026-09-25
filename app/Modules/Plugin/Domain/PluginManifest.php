<?php
namespace App\Modules\Plugin\Domain;

/**
 * plugins/<name>/plugin.json の中身。
 */
final class PluginManifest {
    /** ディレクトリ名。URL (/plugins/<name>) と画面名 (<name>::Page) の頭にもなる */
    public readonly string $name;
    public readonly string $version;
    /** 読み込む ServiceProvider の完全修飾クラス名 */
    public readonly string $provider;
    public readonly bool $enabled;
    /** 画面に出す名前。ロケールごと (ja, en) */
    public readonly array $title;

    /**
     * @param string $name ディレクトリ名
     * @param string $version バージョン
     * @param string $provider ServiceProvider のクラス名
     * @param bool $enabled 読み込むか
     * @param array<string, string> $title ロケールごとの表示名
     */
    public function __construct(string $name, string $version, string $provider, bool $enabled, array $title) {
        $this->name = $name;
        $this->version = $version;
        $this->provider = $provider;
        $this->enabled = $enabled;
        $this->title = $title;
    }

    /**
     * @param string $locale ロケール
     * @return string 表示名。そのロケールが無ければディレクトリ名
     */
    public function titleFor(string $locale): string {
        return $this->title[$locale] ?? $this->name;
    }
}
