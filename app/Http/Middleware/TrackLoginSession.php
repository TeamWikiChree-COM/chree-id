<?php
namespace App\Http\Middleware;

use App\Modules\Device\Application\LoginSessions;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ログイン中の端末を記録する。
 *
 * ログイン時だけ書くと、セッションIDの再生成 (ChreeSession::login) に追従できない。
 * 毎リクエスト書き直すことで、いま生きているセッションIDの行が必ず残る。
 *
 * **応答を返してから書く。** 記録が失敗しても画面は動くべきで、
 * 一覧に出ないことは認証の正しさに影響しない。
 */
class TrackLoginSession {
    private readonly ChreeSession $session;
    private readonly LoginSessions $sessions;

    public function __construct(ChreeSession $session, LoginSessions $sessions) {
        $this->session = $session;
        $this->sessions = $sessions;
    }

    /**
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        return $next($request);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function terminate(Request $request, Response $response): void {
        $accountId = $this->session->accountId();
        if ($accountId === null) return;

        $this->sessions->track($request, $accountId);
    }
}
