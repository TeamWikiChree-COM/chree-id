<?php
namespace App\Support\Locale;

/**
 * 出せる表示言語。
 *
 * 足すのは `config/chreeid.php` の `locales` に1行。
 * ここは「知らない名前を弾く」ためだけに居る (URL や Cookie から任意の文字列が来る)。
 */
final class Locales {
    /**
     * @return list<string> 出せる言語。先頭が既定
     */
    public function available(): array {
        /** @var list<string> $locales */
        $locales = config('chreeid.locales');

        return $locales;
    }

    /**
     * @param string|null $locale 確かめたい名前
     * @return bool 出せる言語か
     */
    public function allows(?string $locale): bool {
        return $locale !== null && in_array($locale, $this->available(), true);
    }

    /**
     * どれとも決められなかったときに出す言語。
     *
     * @return string
     */
    public function fallback(): string {
        return $this->available()[0];
    }
}
