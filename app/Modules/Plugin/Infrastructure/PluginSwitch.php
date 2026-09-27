<?php
namespace App\Modules\Plugin\Infrastructure;

use App\Modules\Plugin\Domain\PluginManifest;
use RuntimeException;

/**
 * プラグインを有効または無効にする。plugin.json の enabled を書き換える。
 *
 * 本番はコマンドもファイルの編集もしにくいので、管理画面から切り替えられるようにするためのもの。
 * 読み込みは起動のたびに plugin.json を見るので、次のリクエストから効く。
 */
class PluginSwitch {
    private readonly PluginRegistry $plugins;

    public function __construct(PluginRegistry $plugins) {
        $this->plugins = $plugins;
    }

    /**
     * enabled 以外の項目と並び順はそのまま残す。手で書いたファイルなので、差分を最小にする。
     *
     * @param PluginManifest $plugin
     * @param bool $enabled
     * @throws RuntimeException 読めない、または書けない場合
     */
    public function set(PluginManifest $plugin, bool $enabled): void {
        $file = $this->plugins->path($plugin, 'plugin.json');
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json)) throw new RuntimeException("plugin.json が読めません: {$file}");

        $json['enabled'] = $enabled;
        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($file, $encoded . "\n", LOCK_EX) === false) throw new RuntimeException("plugin.json に書き込めません: {$file}");

        // 控えは更新時刻で古さを見るが、秒単位なので同じ秒の書き換えを見逃しうる。ここで確実に消す
        $this->plugins->forgetCache();
    }
}
