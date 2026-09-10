<?php
namespace App\Modules\Registry\Http;

use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Modules\Registry\Application\DatabaseMigrations;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Inertia\Inertia;
use Inertia\Response;

/**
 * システム管理のトップ。
 *
 * 利用者自身の設定 (/settings) とは別物なので、名前も入口も分けている。
 * ここに置くのは運営としての操作だけ。
 */
class AdminController {
    public function __construct(private readonly DatabaseMigrations $migrations) {}

    /**
     * @return Response
     */
    public function __invoke(): Response {
        return Inertia::render('Admin/Index', [
            'stats' => [
                'clients' => OAuthClientModel::query()->count(),
                'accounts' => AuthIdentityModel::query()->whereNull('deleted_at')->count(),
                'suspended' => AuthIdentityModel::query()->whereNotNull('suspended_at')->count(),
            ],

            // デプロイ直後は構造が置き去りになる。トップで気付けるようにしておく
            'pendingMigrations' => count($this->migrations->pending()),
        ]);
    }
}
