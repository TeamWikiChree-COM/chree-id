<?php
/**
 * 翻訳 JSON を検証し、server 側から PHP 配列を生成する。CI 用の入口。
 *
 * デプロイのワークフローには PHP の依存解決 (composer install) を置いていないので、
 * artisan を通さずにクラスを直接読む。**中身は `php artisan lang:build` と同じ。**
 */

declare(strict_types=1);

$root = dirname(__DIR__);

foreach (['LangBuildException', 'LangBuildResult', 'LangSource', 'LangValidator', 'LangCompiler', 'LangBuild'] as $class) {
    require_once $root . '/app/Support/Lang/' . $class . '.php';
}

try {
    $result = (new App\Support\Lang\LangBuild())->execute($root . '/lang', $root . '/generated/lang');
} catch (App\Support\Lang\LangBuildException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");

    exit(1);
}

foreach ($result->warnings as $warning) {
    fwrite(STDERR, "警告: {$warning}\n");
}

echo implode(' / ', $result->locales) . " から {$result->files} ファイルを生成しました\n";
