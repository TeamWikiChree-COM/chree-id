<?php
namespace App\Http\Middleware;

use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 停止・退会・削除されたアカウントのセッションをその場で切る。
 *
 * 停止はログインの入口でしか見ていないので、ここが無いと停止前から
 * ログインしていたセッションが使い続けられる。乗っ取られたアカウントを
 * 止めても攻撃者を追い出せないことになるため、要求ごとに確かめる。
 */
class EnsureActiveAccount {
    private readonly ChreeSession $session;
    private readonly AuthIdentityRepository $accounts;

    public function __construct(ChreeSession $session, AuthIdentityRepository $accounts) {
        $this->session = $session;
        $this->accounts = $accounts;
    }

    /**
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        $accountId = $this->session->accountId();
        if ($accountId === null) return $next($request);

        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->isSuspended()) $this->session->logout();

        return $next($request);
    }
}
