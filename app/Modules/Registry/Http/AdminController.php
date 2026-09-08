<?php
namespace App\Modules\Registry\Http;

use App\Modules\Identity\Infrastructure\ChreeAccountModel;
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
    /**
     * @return Response
     */
    public function __invoke(): Response {
        return Inertia::render('Admin/Index', [
            'stats' => [
                'clients' => OAuthClientModel::query()->count(),
                'accounts' => ChreeAccountModel::query()->whereNull('deleted_at')->count(),
                'suspended' => ChreeAccountModel::query()->whereNotNull('suspended_at')->count(),
            ],
        ]);
    }
}
