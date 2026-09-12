<?php

use App\Support\Lang\LangBuild;
use App\Support\Lang\LangBuildException;

/**
 * 翻訳のビルド。**実行するまで気づけない食い違いをここで落とす**のが要なので、
 * 落ちるべきものが落ちることを確かめる。
 */
class LangBuildTest extends \Tests\TestCase {
    private string $dir;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/chreeid-lang-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/src', 0o775, true);
    }

    #[\Override]
    protected function tearDown(): void {
        $this->removeTree($this->dir);
        parent::tearDown();
    }

    /**
     * @param string $path 消す対象
     * @return void
     */
    private function removeTree(string $path): void {
        if (!is_dir($path)) {
            unlink($path);

            return;
        }

        foreach (glob($path . '/*') ?: [] as $child) {
            $this->removeTree($child);
        }

        rmdir($path);
    }

    /**
     * @param array<string, array<string, string>> $locales
     */
    private function build(array $locales): \App\Support\Lang\LangBuildResult {
        foreach ($locales as $name => $messages) {
            file_put_contents($this->dir . "/src/{$name}.json", json_encode($messages, JSON_THROW_ON_ERROR));
        }

        return (new LangBuild())->execute($this->dir . '/src', $this->dir . '/out');
    }

    // 第1セグメントでファイルを分け、残りをネストさせないと __() から引けない
    public function test_splitsFlatKeysByTheirFirstSegment(): void {
        $this->build([
            'ja_jp' => ['account.login.title' => 'ログイン'],
            'en_us' => ['account.login.title' => 'Sign in'],
        ]);

        $tree = require $this->dir . '/out/ja/account.php';

        $this->assertSame(['login' => ['title' => 'ログイン']], $tree);
        $this->assertFileExists($this->dir . '/out/en/account.php');
    }

    // 自前で連結すると引用符で壊れる。var_export に任せていることの確認
    public function test_keepsQuotesIntact(): void {
        $this->build([
            'ja_jp' => ['a.b' => "I'm here \ \"x\""],
            'en_us' => ['a.b' => "I'm here \ \"x\""],
        ]);

        $tree = require $this->dir . '/out/ja/a.php';

        $this->assertIsArray($tree);
        $this->assertSame("I'm here \ \"x\"", $tree['b']);
    }

    public function test_failsWhenATranslationIsMissing(): void {
        $this->expectException(LangBuildException::class);
        $this->expectExceptionMessageMatches('/en_us\.json .* "a\.b"/u');

        $this->build(['ja_jp' => ['a.b' => 'あ'], 'en_us' => []]);
    }

    // 実行するまで気づけない種類のバグなので、ビルドで落とす
    public function test_failsWhenAPlaceholderIsDropped(): void {
        $this->expectException(LangBuildException::class);
        $this->expectExceptionMessageMatches('/:name/u');

        $this->build([
            'ja_jp' => ['a.b' => ':name さん'],
            'en_us' => ['a.b' => 'Hello'],
        ]);
    }

    // ドットが無いと PHP ローダーはファイル名として探せない
    public function test_failsOnAKeyWithoutADot(): void {
        $this->expectException(LangBuildException::class);

        $this->build(['ja_jp' => ['welcome' => 'ようこそ'], 'en_us' => ['welcome' => 'Welcome']]);
    }

    // 翻訳が先行することがあるので、余分なキーは落とさず知らせるだけ
    public function test_warnsAboutAnExtraKey(): void {
        $result = $this->build([
            'ja_jp' => ['a.b' => 'あ'],
            'en_us' => ['a.b' => 'a', 'a.c' => 'c'],
        ]);

        $this->assertCount(1, $result->warnings);
    }
}
