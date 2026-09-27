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

    /** アイコンの形に使う SVG (data URI)。あれば Font Awesome より優先する */
    public ?string $svg;

    /** SVG を塗る色 (#rrggbb)。無ければ周りの文字色 */
    public ?string $color;

    /**
     * @param array<string, string> $label ロケール => 名前 (例: ['ja' => 'Google', 'en' => 'Google'])。plugin.json の title と同じ形
     * @param string $icon Font Awesome のアイコン名。svg があるときは使えない画面向けの代わり
     * @param string $family アイコンの字形の系統。ブランドのマークなら brands
     * @param string|null $svg アイコンの形に使う SVG (data URI)
     * @param string|null $color SVG を塗る色 (#rrggbb)
     */
    public function __construct(array $label, string $icon, string $family = self::FAMILY_SOLID, ?string $svg = null, ?string $color = null) {
        $this->label = $label;
        $this->icon = $icon;
        $this->family = $family;
        $this->svg = $svg;
        $this->color = $color;
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
     * SVG のファイルをアイコンの形にする。画面では形だけを使い、$color か周りの文字色で塗る。
     *
     * @param string $path SVG のファイル
     * @param string|null $color 塗る色 (#rrggbb)。ブランドの色が決まっているとき
     * @return self
     */
    public function withSvgFile(string $path, ?string $color = null): self {
        $svg = (string) file_get_contents($path);

        return new self($this->label, $this->icon, $this->family, 'data:image/svg+xml;base64,' . base64_encode($svg), $color);
    }

    /**
     * @param string $locale
     * @return array{label: string, icon: string, family: string, svg: string|null, color: string|null}
     */
    public function toArray(string $locale): array {
        return [
            'label' => $this->label[$locale] ?? array_values($this->label)[0] ?? '',
            'icon' => $this->icon,
            'family' => $this->family,
            'svg' => $this->svg,
            'color' => $this->color,
        ];
    }
}
