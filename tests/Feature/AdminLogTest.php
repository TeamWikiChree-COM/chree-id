<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LogsInAsAdmin;
use Tests\TestCase;

// 管理画面のアプリケーションログ
class AdminLogTest extends TestCase {
    use LogsInAsAdmin;
    use RefreshDatabase;

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
}
