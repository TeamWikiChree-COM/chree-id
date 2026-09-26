<?php
namespace App\Modules\Plugin\Domain;

use LogicException;

/**
 * プラグインが本体の画面へ足すリンク。
 *
 * **本体の画面へ差し込めるのはここだけ。** プラグインが本体の画面やロジックを
 * 直接書き換えられると、本体の更新で黙って壊れる。
 */
final class PluginMenu {
    /** ダッシュボードの「利用可能なプラグイン」。ログイン中の利用者向け */
    public const AREA_DASHBOARD = 'dashboard';

    /** 管理画面のトップ。運営向け */
    public const AREA_ADMIN = 'admin';

    /** @var list<PluginMenuItem> */
    private array $items = [];

    /** @var array<string, PluginManifest> 名前をキーにした、読み込んだプラグイン。addPlugin() で名前から引く */
    private array $plugins = [];

    /**
     * 読み込んだプラグインを教える。本体の読み込み処理が呼ぶ。
     *
     * @param PluginManifest $plugin
     */
    public function registerPlugin(PluginManifest $plugin): void {
        $this->plugins[$plugin->name] = $plugin;
    }

    /**
     * 入口の名前や URL を自分で決めたいときに使う。ふつうは addPlugin() で足りる。
     *
     * @param PluginMenuItem $item
     */
    public function add(PluginMenuItem $item): void {
        $this->items[] = $item;
    }

    /**
     * プラグインの入口を足す。名前と説明は plugin.json の title と description、URL は /plugins/<名前>。
     *
     * @param string $name プラグインのフォルダ名
     * @param string $area 上の AREA_* のいずれか
     * @param list<string> $clientIds このどれかと連携している利用者にだけ出す。空なら全員
     * @throws LogicException 読み込まれていないプラグインの名前を渡したとき (打ち間違いで入口が黙って消えないように)
     */
    public function addPlugin(string $name, string $area, array $clientIds = []): void {
        $plugin = $this->plugins[$name] ?? throw new LogicException("Unknown plugin: {$name}");

        $this->add(new PluginMenuItem($area, "/plugins/{$name}", $plugin->title, $plugin->description, $clientIds));
    }

    /**
     * @param string $area 上の AREA_* のいずれか
     * @param string $locale 今のロケール
     * @param list<string> $connectedClientIds 利用者が連携しているサービスの client_id
     * @return list<array{href: string, label: string, description: string|null}>
     */
    public function itemsFor(string $area, string $locale, array $connectedClientIds = []): array {
        $result = [];

        foreach ($this->items as $item) {
            if ($item->area !== $area || !$item->isAvailableTo($connectedClientIds)) continue;

            $result[] = [
                'href' => $item->href,
                'label' => $item->labelFor($locale),
                'description' => $item->descriptionFor($locale),
            ];
        }

        return $result;
    }
}
