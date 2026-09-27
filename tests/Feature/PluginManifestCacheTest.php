<?php
namespace Tests\Feature;

use App\Modules\Plugin\Infrastructure\PluginManifestCache;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use Illuminate\Support\Facades\File;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// plugins/ を探した結果の控え
class PluginManifestCacheTest extends TestCase {
    private string $root;
    private string $cacheFile;

    #[Override]
    protected function setUp(): void {
        parent::setUp();

        $dir = sys_get_temp_dir() . '/chreeid-plugin-cache-' . bin2hex(random_bytes(6));
        $this->root = $dir . '/plugins';
        $this->cacheFile = $dir . '/plugins.php';
        File::copyDirectory(base_path('tests/Fixtures/plugins'), $this->root);
    }

    #[Override]
    protected function tearDown(): void {
        File::deleteDirectory(dirname($this->root));

        parent::tearDown();
    }

    /**
     * @return PluginRegistry
     */
    private function registry(): PluginRegistry {
        clearstatcache();

        return new PluginRegistry($this->root, new PluginManifestCache($this->cacheFile));
    }

    /**
     * plugin.json の中身を書き換えるが、更新時刻は元に戻す。控えを使っているかを見分けるため
     *
     * @param string $title
     */
    private function retitleKeepingMtime(string $title): void {
        $file = $this->root . '/example/plugin.json';
        $mtime = (int) filemtime($file);
        $json = json_decode((string) file_get_contents($file), true);
        $json['title'] = ['ja' => $title, 'en' => $title];
        file_put_contents($file, json_encode($json));
        touch($file, $mtime);
    }

    #[TestDox('一度探した結果は控えから読み、plugin.json を読み直さない')]
    public function test_readsFromCache(): void {
        $this->assertSame('例', $this->registry()->find('example')?->titleFor('ja'));
        $this->assertFileExists($this->cacheFile);

        $this->retitleKeepingMtime('変えた');

        $this->assertSame('例', $this->registry()->find('example')?->titleFor('ja'));
    }

    #[TestDox('plugin.json の更新時刻が変われば読み直す')]
    public function test_rereadsWhenManifestChanges(): void {
        $this->registry();
        $this->retitleKeepingMtime('変えた');
        touch($this->root . '/example/plugin.json', time() + 10);

        $this->assertSame('変えた', $this->registry()->find('example')?->titleFor('ja'));
    }

    #[TestDox('プラグインのフォルダが増えれば読み直す')]
    public function test_rereadsWhenPluginIsAdded(): void {
        $this->registry();

        File::copyDirectory($this->root . '/example', $this->root . '/second');
        touch($this->root, time() + 10);

        $this->assertNotNull($this->registry()->find('second'));
    }

    #[TestDox('控えを消せば読み直す')]
    public function test_forgetCache(): void {
        $registry = $this->registry();
        $this->retitleKeepingMtime('変えた');

        $registry->forgetCache();

        $this->assertSame('変えた', $this->registry()->find('example')?->titleFor('ja'));
    }

    #[TestDox('別の置き場の控えは使わない')]
    public function test_ignoresCacheForOtherRoot(): void {
        $this->registry();

        $other = dirname($this->root) . '/other';
        File::copyDirectory($this->root, $other);
        $this->retitleKeepingMtime('変えた');
        copy($this->root . '/example/plugin.json', $other . '/example/plugin.json');

        $registry = new PluginRegistry($other, new PluginManifestCache($this->cacheFile));
        $this->assertSame('変えた', $registry->find('example')?->titleFor('ja'));
    }
}
