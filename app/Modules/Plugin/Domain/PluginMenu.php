<?php
namespace App\Modules\Plugin\Domain;

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

    /**
     * @param PluginMenuItem $item
     */
    public function add(PluginMenuItem $item): void {
        $this->items[] = $item;
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
