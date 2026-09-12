<?php

namespace App\Console\Commands;

use App\Support\Lang\LangBuild;
use App\Support\Lang\LangBuildException;
use Illuminate\Console\Command;

/**
 * 翻訳 JSON から PHP 配列を生成する。
 *
 * 生成物は履歴に入れず、CI がデプロイ直前に作って `.deploy-include` から直接送る
 * (Vite のアセットと同じ扱い)。**ローカルでも叩けるようにここに置く。**
 */
class BuildLang extends Command {
    #[\Override]
    protected $signature = 'lang:build';

    #[\Override]
    protected $description = '翻訳 JSON を検証して PHP 配列を生成する';

    /**
     * @param LangBuild $build ビルドの本体
     * @return int
     */
    public function handle(LangBuild $build): int {
        try {
            $result = $build->execute(base_path('lang'), base_path('generated/lang'));
        } catch (LangBuildException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        $this->info(
            implode(' / ', $result->locales) . " から {$result->files} ファイルを生成しました"
        );

        return self::SUCCESS;
    }
}
