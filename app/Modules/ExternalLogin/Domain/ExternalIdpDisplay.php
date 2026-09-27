<?php
namespace App\Modules\ExternalLogin\Domain;

/**
 * 外部 IdP を画面に出すときの名前とアイコン。
 *
 * フロントに固定の一覧を持たせると、プラグインで追加した IdP が識別子のまま出てしまう。
 * IdP 自身に持たせ、共有 props で配る。
 */
readonly class ExternalIdpDisplay {
    public const FAMILY_BRANDS = 'brands';
    public const FAMILY_SOLID = 'solid';

    /** @var array<string, string> ロケール => 名前 */
    public array $label;
    public string $icon;
    public string $family;

    /**
     * @param array<string, string> $label ロケール => 名前 (例: ['ja' => 'Google', 'en' => 'Google'])。plugin.json の title と同じ形
     * @param string $icon Font Awesome のアイコン名
     * @param string $family アイコンの字形の系統。ブランドのマークなら brands
     */
    public function __construct(array $label, string $icon, string $family = self::FAMILY_SOLID) {
        $this->label = $label;
        $this->icon = $icon;
        $this->family = $family;
    }

    /**
     * 名前が1つで言語によって変わらない IdP 向け。
     *
     * @param string $name ブランド名など
     * @param string $icon
     * @param string $family
     * @return self
     */
    public static function brand(string $name, string $icon, string $family = self::FAMILY_BRANDS): self {
        return new self(['ja' => $name, 'en' => $name], $icon, $family);
    }

    /**
     * @param string $locale
     * @return array{label: string, icon: string, family: string}
     */
    public function toArray(string $locale): array {
        return [
            'label' => $this->label[$locale] ?? array_values($this->label)[0] ?? '',
            'icon' => $this->icon,
            'family' => $this->family,
        ];
    }
}
