<?php
namespace Tests\Feature;

use App\Modules\Admin\Infrastructure\LogFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LogsInAsAdmin;
use Tests\TestCase;

// 管理画面のアプリケーションログ
class AdminLogTest extends TestCase {
    use LogsInAsAdmin;
    use RefreshDatabase;

    #[TestDox('ログ本体は最初の描画のあとに届く')]
    public function test_deliversEntriesAfterTheFirstRender(): void {
        $this->loginAsAdmin();

        // 最初の描画ではスケルトンを出すため、重い entries は後から届く
        $this->get('/admin/logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs/Index')
                ->has('files')
                ->missing('entries')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('entries')));
    }

    // 「記録がありません」だけでは、壊れていないのか書けていないのか見分けられない
    public function test_diagnosesALevelThatDropsWarnings(): void {
        config([
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['single'],
            'logging.channels.single.level' => 'error',
        ]);

        $diagnosis = app(LogFile::class)->diagnosis();

        $this->assertTrue($diagnosis['toFile']);
        $this->assertSame('single', $diagnosis['channel']);
        $this->assertSame('error', $diagnosis['level']);
    }

    public function test_diagnosesAChannelThatDoesNotWriteFiles(): void {
        config(['logging.default' => 'stderr']);

        $diagnosis = app(LogFile::class)->diagnosis();

        $this->assertFalse($diagnosis['toFile']);
        $this->assertSame('stderr', $diagnosis['channel']);
    }
}
