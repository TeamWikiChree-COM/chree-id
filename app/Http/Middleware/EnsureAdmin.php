<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Registry\Domain\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理画面への入口を絞る。
 *
 * 権限が無い場合は 404 を返す。403 だと「そこに管理画面がある」ことだけが伝わるため。
 */
class EnsureAdmin {
    public function __construct(
        private readonly ChreeSession $session,
        private readonly AuthIdentityRepository $accounts,
        private readonly AdminAccess $access,
    ) {}

    /**
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        if (!$this->access->allows($this->accounts->findById($accountId))) {
            abort(404);
        }

        return $next($request);
    }
}
