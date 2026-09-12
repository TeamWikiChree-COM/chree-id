<?php
namespace App\Modules\Device\Domain;

/**
 * User-Agent を人が見て分かる名前にする。
 *
 * 端末を特定するためではなく、一覧の中から自分がいま使っているものを
 * 見分けられれば足りる。判定の精度より、外した時に嘘をつかないことを優先し、
 * 分からなければ「不明な端末」に倒す。
 */
class DeviceLabel {
    /** 先に当たったものを採用する。Edge は Chrome を名乗るので順番が意味を持つ */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'Firefox/' => 'Firefox',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const PLATFORMS = [
        'Windows' => 'Windows',
        'Android' => 'Android',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Macintosh' => 'macOS',
        'Linux' => 'Linux',
    ];

    /**
     * @param string|null $userAgent ブラウザが名乗った User-Agent
     * @return string 「Chrome (Windows)」のような表示名
     */
    public static function from(?string $userAgent): string {
        if ($userAgent === null || trim($userAgent) === '') return __('device.unknown');

        $browser = self::match($userAgent, self::BROWSERS);
        $platform = self::match($userAgent, self::PLATFORMS);

        if ($browser === null && $platform === null) return __('device.unknown');
        if ($browser === null) return (string) $platform;
        if ($platform === null) return $browser;

        return "{$browser} ({$platform})";
    }

    /**
     * @param string $userAgent
     * @param array<string, string> $table 探す断片 => 表示名
     * @return string|null 当たらなければ null
     */
    private static function match(string $userAgent, array $table): ?string {
        foreach ($table as $needle => $label) {
            if (str_contains($userAgent, $needle)) return $label;
        }

        return null;
    }
}
