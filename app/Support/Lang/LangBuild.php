<?php
namespace App\Support\Lang;

/**
 * 読む → 検証する → 生成する、の一続き。
 *
 * artisan からも CI の素の PHP からも同じものを呼ぶ。
 * **フレームワークに依存させない**ので、ここに Laravel のものを持ち込まないこと。
 */
final class LangBuild {
    public function __construct(
        private readonly LangSource $source = new LangSource(),
        private readonly LangValidator $validator = new LangValidator(),
        private readonly LangCompiler $compiler = new LangCompiler(),
    ) {}

    /**
     * @param string $sourceDir lang/ のパス
     * @param string $outDir generated/lang のパス
     * @return LangBuildResult
     * @throws LangBuildException 落とすべき食い違いがあった
     */
    public function execute(string $sourceDir, string $outDir): LangBuildResult {
        $locales = $this->source->load($sourceDir);
        $warnings = $this->validator->validate($locales);
        $files = $this->compiler->compile($locales, $outDir);

        return new LangBuildResult(array_keys($locales), $files, $warnings);
    }
}
