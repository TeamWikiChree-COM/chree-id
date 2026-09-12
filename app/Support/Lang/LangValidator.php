<?php
namespace App\Support\Lang;

/**
 * ロケール間の食い違いをビルド時に落とす。
 *
 * ARCHITECTURE.md 10.3。**プレースホルダの漏れは実行するまで気づけない**ので、
 * ここで落とす価値が高い。余分なキーは翻訳が先行することがあるので警告に留める。
 */
final class LangValidator {
    /**
     * @param array<string, array<string, string>> $locales ロケール => キー => 文言
     * @param bool $requireFileSegment キーにドットを要求するか (PHP ローダー向けのみ true)
     * @return list<string> 警告。異常があれば例外を投げる
     * @throws LangBuildException 基準ロケールとの食い違いがある
     */
    public function validate(array $locales, bool $requireFileSegment = true): array {
        $base = $locales[LangSource::BASE];
        $errors = $requireFileSegment ? $this->keysWithoutFile($base) : [];
        $warnings = [];

        foreach ($locales as $locale => $messages) {
            if ($locale === LangSource::BASE) continue;

            foreach ($base as $key => $text) {
                if (!isset($messages[$key])) {
                    $errors[] = "{$locale}.json に \"{$key}\" がありません";

                    continue;
                }

                $errors = array_merge($errors, $this->placeholders($locale, $key, $text, $messages[$key]));
            }

            foreach (array_keys($messages) as $key) {
                if (!isset($base[$key])) $warnings[] = "{$locale}.json の \"{$key}\" は基準に無いキーです";
            }
        }

        if ($errors !== []) throw new LangBuildException(implode("\n", $errors));

        return $warnings;
    }

    /**
     * Laravel の PHP ローダーは第1セグメントをファイル名として探すので、
     * ドットを持たないキーは __() から引けない。書いた本人が気づけないので落とす。
     *
     * @param array<string, string> $base 基準ロケール
     * @return list<string>
     */
    private function keysWithoutFile(array $base): array {
        $errors = [];

        foreach (array_keys($base) as $key) {
            if (!str_contains($key, '.')) {
                $errors[] = "\"{$key}\" にドットがありません (PHP ローダーは第1セグメントをファイル名として扱う)";
            }
        }

        return $errors;
    }

    /**
     * @param string $locale 比べる相手のロケール
     * @param string $key キー
     * @param string $base 基準ロケールの文言
     * @param string $text 相手の文言
     * @return list<string>
     */
    private function placeholders(string $locale, string $key, string $base, string $text): array {
        $errors = [];

        foreach ($this->extract($base) as $name) {
            if (!in_array($name, $this->extract($text), true)) {
                $errors[] = "{$locale}.json の \"{$key}\" に :{$name} がありません";
            }
        }

        return $errors;
    }

    /**
     * @param string $text 文言
     * @return list<string> ":name" 形式の名前
     */
    private function extract(string $text): array {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $text, $matches);

        return $matches[1];
    }
}
