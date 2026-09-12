<?php
namespace App\Support\Lang;

/**
 * ビルドの結果。呼び出し元が人に見せるためだけに使う。
 */
final class LangBuildResult {
    /**
     * @param list<string> $locales 読んだロケール
     * @param int $files 書き出したファイル数
     * @param list<string> $warnings 落とすほどではない食い違い
     */
    public function __construct(
        public readonly array $locales,
        public readonly int $files,
        public readonly array $warnings,
    ) {}
}
