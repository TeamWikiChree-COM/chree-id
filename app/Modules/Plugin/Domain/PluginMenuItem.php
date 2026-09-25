<?php
namespace App\Modules\Plugin\Domain;

/**
 * プラグインが本体の画面へ足すリンク1件。
 */
final class PluginMenuItem {
    public readonly string $area;
    /** 行き先。/plugins/<name>/… を指すこと */
    public readonly string $href;
    /** @var array<string, string> ロケールごとの文言 (ja, en) */
    public readonly array $label;
    /** @var array<string, string> ロケールごとの説明。無ければ空 */
    public readonly array $description;
    /**
     * このどれかと連携している利用者にだけ出す client_id。空なら全員に出す。
     *
     * @var list<string>
     */
    public readonly array $clientIds;

    /**
     * @param string $area PluginMenu::AREA_* のいずれか
     * @param string $href 行き先
     * @param array<string, string> $label ロケールごとの文言
     * @param array<string, string> $description ロケールごとの説明
     * @param list<string> $clientIds 連携していれば使えるサービスの client_id
     */
    public function __construct(string $area, string $href, array $label, array $description = [], array $clientIds = []) {
        $this->area = $area;
        $this->href = $href;
        $this->label = $label;
        $this->description = $description;
        $this->clientIds = $clientIds;
    }

    /**
     * @param list<string> $connectedClientIds 利用者が連携しているサービスの client_id
     * @return bool
     */
    public function isAvailableTo(array $connectedClientIds): bool {
        if ($this->clientIds === []) return true;

        return array_intersect($this->clientIds, $connectedClientIds) !== [];
    }

    /**
     * @param string $locale ロケール
     * @return string そのロケールが無ければ最初に書かれたもの
     */
    public function labelFor(string $locale): string {
        return $this->label[$locale] ?? array_values($this->label)[0] ?? '';
    }

    /**
     * @param string $locale ロケール
     * @return string|null
     */
    public function descriptionFor(string $locale): ?string {
        if ($this->description === []) return null;

        return $this->description[$locale] ?? array_values($this->description)[0];
    }
}
