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
