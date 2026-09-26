<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LogsInAsAdmin;
use Tests\TestCase;

// 管理画面のバックアップ一覧
class AdminBackupPageTest extends TestCase {
    use LogsInAsAdmin;
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();

        Config::set('chreeid.backup.key', 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
        Config::set('chreeid.backup.drive', [
            'client_id' => 'client',
            'client_secret' => 'secret',
            'refresh_token' => 'refresh',
            'folder_id' => 'folder',
        ]);
        Config::set('chreeid.backup.local.enabled', false);
    }

    public function test_asksDriveOnlyAfterTheFirstRender(): void {
        Http::fake();
        $this->loginAsAdmin();

        $this->get('/admin/backups')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Backups/Index')
                ->missing('drive'));

        // 画面を先に出すのが目的なので、最初の描画で Drive を待っていたら意味がない
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_showsWhyTheDriveListCouldNotBeRead(): void {
        Http::fake(['*' => Http::response('down', 500)]);
        $this->loginAsAdmin();

        $this->get('/admin/backups')->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('drive.backups', [])
                ->whereType('drive.error', 'string')));
    }
}
