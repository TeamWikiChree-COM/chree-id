<?php
namespace App\Support\Lang;

/**
 * 読む → 検証する → 生成する、の一続き。
 *
 * artisan からも CI の素の PHP からも同じものを呼ぶ。
 * **フレームワークに依存させない**ので、ここに Laravel のものを持ち込まないこと。
 *
 * **正は client / server に分けてある。** クライアント側の文言は Vite が
 * バンドルへ畳み込むので、PHP に落とすのはサーバ側だけでよい。
 * 分けないと、メール本文やサーバ間 API の文言まで全利用者のブラウザに配られる。
 * **検証は両方に掛ける。** ロケール間の欠けはどちらで起きても困る。
 */
final class LangBuild {
    public function __construct(
        private readonly LangSource $source = new LangSource(),
        private readonly LangValidator $validator = new LangValidator(),
        private readonly LangCompiler $compiler = new LangCompiler(),
    ) {}

    /**
     * @param string $langDir lang/ のパス (client/ と server/ を持つ)
     * @param string $outDir generated/lang のパス
     * @return LangBuildResult
     * @throws LangBuildException 落とすべき食い違いがあった
     */
    public function execute(string $langDir, string $outDir): LangBuildResult {
        $root = rtrim($langDir, '/');

        // クライアント側はドットを要求しない。第1セグメントをファイル名にするのは
        // PHP ローダーの都合で、Vite にその制約は無い
        $client = $this->source->load($root . '/client');
        $warnings = $this->validator->validate($client, requireFileSegment: false);

        $server = $this->source->load($root . '/server');
        $warnings = array_merge($warnings, $this->validator->validate($server));

        $files = $this->compiler->compile($server, $outDir);

        return new LangBuildResult(array_keys($server), $files, $warnings);
    }
}
