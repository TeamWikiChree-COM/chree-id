<?php
namespace App\Support\Lang;

/**
 * 翻訳の正 (source of truth) である JSON を読む。
 *
 * ARCHITECTURE.md 10章。**編集するのはここだけ**で、PHP はビルド成果物。
 * フレームワークに依存させない。CI からは artisan を通さずに直接呼ぶため
 * (デプロイのワークフローには PHP の依存解決を置いていない)。
 */
final class LangSource {
    /** 基準ロケール。ここに有るキーが他に無ければビルドを落とす */
    public const BASE = 'ja_jp';

    /**
     * @param string $dir lang/ のパス
     * @return array<string, array<string, string>> ロケール => キー => 文言
     * @throws LangBuildException 読めない、または JSON として壊れている
     */
    public function load(string $dir): array {
        $files = glob(rtrim($dir, '/') . '/*.json');
        if ($files === false || $files === []) {
            throw new LangBuildException("翻訳ファイルが1つもありません: {$dir}");
        }

        $locales = [];

        foreach ($files as $path) {
            $locales[basename($path, '.json')] = $this->read($path);
        }

        if (!isset($locales[self::BASE])) {
            throw new LangBuildException('基準ロケールがありません: ' . self::BASE . '.json');
        }

        return $locales;
    }

    /**
     * @param string $path JSON のパス
     * @return array<string, string>
     * @throws LangBuildException
     */
    private function read(string $path): array {
        $raw = file_get_contents($path);
        if ($raw === false) throw new LangBuildException("読み込みに失敗しました: {$path}");

        try {
            /** @var array<string, string> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new LangBuildException("JSON が壊れています: {$path}: {$e->getMessage()}", 0, $e);
        }

        // キー順で差分が暴れるのを防ぐ
        ksort($data);

        return $data;
    }
}
