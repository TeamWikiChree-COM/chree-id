<?php
namespace Tests\Feature;

use App\Modules\Backup\Application\BackupCipher;
use App\Modules\Backup\Application\ExportDatabase;
use App\Modules\Backup\Application\RunBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * バックアップ。
 *
 * **中身はほぼ資格情報**なので、暗号化されていること・鍵が違えば開かないことを
 * 何より確かめる。世代の整理は「置いたあとに消す」順序が要。
 */
class BackupTest extends TestCase {
    use RefreshDatabase;

    private const KEY = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

    private string $localPath;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();

        Config::set('chreeid.backup.key', self::KEY);
        Config::set('chreeid.backup.keep', 2);
        Config::set('chreeid.backup.drive', [
            'client_id' => 'client',
            'client_secret' => 'secret',
            'refresh_token' => 'refresh',
            'folder_id' => 'folder',
        ]);

        // 並列実行で取り合わないよう、テストごとに別の置き場を使う
        $this->localPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chreeid-backup-' . bin2hex(random_bytes(6));
        Config::set('chreeid.backup.local', ['enabled' => true, 'days' => 7, 'path' => $this->localPath]);
    }

    #[\Override]
    protected function tearDown(): void {
        foreach (glob($this->localPath . '/*') ?: [] as $file) unlink($file);
        if (is_dir($this->localPath)) rmdir($this->localPath);

        parent::tearDown();
    }

    // セッションを戻すと、他人のログイン状態まで蘇る
    public function test_leavesOutTablesThatMustNotComeBack(): void {
        $dump = app(ExportDatabase::class)->execute();

        foreach (['sessions', 'cache', 'jobs', 'migrations'] as $table) {
            $this->assertArrayNotHasKey($table, $dump['tables']);
        }

        $this->assertArrayHasKey('auth_identities', $dump['tables']);
    }

    public function test_sealsAndOpensTheSameContent(): void {
        $cipher = app(BackupCipher::class);

        $sealed = $cipher->seal(['tables' => ['a' => [['secret' => 'ひみつ']]]]);

        $this->assertStringNotContainsString('ひみつ', $sealed);
        $this->assertSame(['tables' => ['a' => [['secret' => 'ひみつ']]]], $cipher->open($sealed));
    }

    public function test_refusesADifferentKey(): void {
        $sealed = app(BackupCipher::class)->seal(['a' => 1]);

        Config::set('chreeid.backup.key', base64_encode(str_repeat('x', 32)));

        $this->expectException(RuntimeException::class);
        app(BackupCipher::class)->open($sealed);
    }

    // GCM なので改竄も復号の失敗として出る。黙って壊れた控えを戻さない
    public function test_refusesATamperedBackup(): void {
        $cipher = app(BackupCipher::class);
        /** @var array<string, string> $envelope */
        $envelope = json_decode($cipher->seal(['a' => 1]), true, 512, JSON_THROW_ON_ERROR);

        $envelope['data'] = base64_encode(base64_decode($envelope['data'], true) . 'x');

        $this->expectException(RuntimeException::class);
        $cipher->open(json_encode($envelope, JSON_THROW_ON_ERROR));
    }

    public function test_refusesAKeyOfTheWrongLength(): void {
        Config::set('chreeid.backup.key', base64_encode('short'));

        $this->expectException(RuntimeException::class);
        app(BackupCipher::class)->seal(['a' => 1]);
    }

    // 鍵が無いなら取らない。暗号化できない控えを外へ出すくらいなら無いほうがよい
    public function test_doesNotRunWithoutAKey(): void {
        Config::set('chreeid.backup.key', null);
        Http::fake();

        $this->expectException(RuntimeException::class);
        app(RunBackup::class)->execute();
    }

    public function test_keepsAnEncryptedCopyOnTheServer(): void {
        Config::set('chreeid.backup.drive', []);
        Http::fake();

        $result = app(RunBackup::class)->execute();
        $path = $this->localPath . DIRECTORY_SEPARATOR . $result->name;

        $this->assertFileExists($path);
        $this->assertStringNotContainsString('auth_identities', (string) file_get_contents($path));
        Http::assertNothingSent();
    }

    // Drive が落ちた日にもサーバ内の控えは残る。失敗は失敗として伝える
    public function test_keepsTheLocalCopyWhenDriveFails(): void {
        Http::fake(['*' => Http::response('down', 500)]);

        try {
            app(RunBackup::class)->execute();
            $this->fail('Drive の失敗が伝わっていない');
        } catch (RuntimeException) {
            $this->assertCount(1, glob($this->localPath . '/chreeid-*.json.enc') ?: []);
        }
    }

    public function test_prunesLocalCopiesOlderThanTheKeptDays(): void {
        Config::set('chreeid.backup.drive', []);
        Config::set('chreeid.backup.local.days', 7);
        mkdir($this->localPath, 0700, true);

        $old = $this->localPath . '/chreeid-20260101-000000.json.enc';
        $recent = $this->localPath . '/chreeid-20260102-000000.json.enc';
        $foreign = $this->localPath . '/keep-me.txt';
        foreach ([$old, $recent, $foreign] as $file) file_put_contents($file, 'x');
        touch($old, now()->subDays(8)->getTimestamp());
        touch($foreign, now()->subDays(30)->getTimestamp());

        $result = app(RunBackup::class)->execute();

        $this->assertSame(['chreeid-20260101-000000.json.enc'], $result->pruned);
        $this->assertFileExists($recent);
        $this->assertFileExists($foreign);
    }

    public function test_refusesWhenNoDestinationIsAvailable(): void {
        Config::set('chreeid.backup.drive', []);
        Config::set('chreeid.backup.local.enabled', false);

        $this->expectException(RuntimeException::class);
        app(RunBackup::class)->execute();
    }

    public function test_uploadsAnEncryptedDump(): void {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token']),
            'googleapis.com/upload/*' => Http::response(['id' => 'file-1']),
            'googleapis.com/drive/v3/files*' => Http::response(['files' => []]),
        ]);

        $result = app(RunBackup::class)->execute();

        $this->assertStringEndsWith('.json.enc', $result->name);
        $this->assertGreaterThan(0, $result->tables);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/upload/')) return false;

            // 平文が混じっていないこと。テーブル名すら出てはいけない
            return !str_contains((string) $request->body(), 'auth_identities');
        });
    }

    /**
     * 残す世代を超えた分だけ消す。
     *
     * **置いたあとに消す。** 先に消すと、置くのに失敗したときに世代が1つ減る。
     */
    public function test_prunesOnlyWhatExceedsTheKeptGenerations(): void {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token']),
            'googleapis.com/upload/*' => Http::response(['id' => 'new']),
            'googleapis.com/drive/v3/files?*' => Http::response(['files' => [
                ['id' => 'a', 'name' => 'new.json.enc', 'createdTime' => '2026-09-12T00:00:00Z'],
                ['id' => 'b', 'name' => 'mid.json.enc', 'createdTime' => '2026-09-11T00:00:00Z'],
                ['id' => 'c', 'name' => 'old.json.enc', 'createdTime' => '2026-09-10T00:00:00Z'],
            ]]),
            'googleapis.com/drive/v3/files/*' => Http::response([]),
        ]);

        $result = app(RunBackup::class)->execute();

        $this->assertSame(['old.json.enc'], $result->pruned);

        Http::assertSent(fn (\Illuminate\Http\Client\Request $r): bool =>
            $r->method() === 'DELETE' && str_ends_with($r->url(), '/files/c'));
    }
}
