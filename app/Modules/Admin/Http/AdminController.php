<?php
namespace App\Modules\Admin\Http;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Application\AdminAccountQueries;
use App\Modules\Admin\Application\DatabaseMigrations;
use App\Modules\Plugin\Domain\PluginMenu;
use Inertia\Inertia;
use Inertia\Response;

/**
 * システム管理のトップ。
 *
 * 利用者自身の設定 (/settings) とは別物なので、名前も入口も分けている。
 * ここに置くのは運営としての操作だけ。
 */
class AdminController extends Controller {
    private readonly DatabaseMigrations $migrations;
    private readonly AdminAccountQueries $queries;
    private readonly PluginMenu $plugins;

    public function __construct(DatabaseMigrations $migrations, AdminAccountQueries $queries, PluginMenu $plugins) {
        $this->migrations = $migrations;
        $this->queries = $queries;
        $this->plugins = $plugins;
    }

    /**
     * @return Response
     */
    public function __invoke(): Response {
        return Inertia::render('Admin/Index', [
            'stats' => $this->queries->stats(),

            // デプロイ直後は構造が置き去りになる。トップで気付けるようにしておく
            'pendingMigrations' => count($this->migrations->pending()),

            // プラグインが追加した運営向けの画面
            'plugins' => $this->plugins->itemsFor(PluginMenu::AREA_ADMIN, app()->getLocale()),
        ]);
    }
}
