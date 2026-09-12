<?php
namespace App\Modules\Audit\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 本人が見る記録。
 *
 * **運営向けの一覧 (AdminAuditController) とは出す範囲が違う。**
 * ここは自分についての行だけ。他人の記録が混ざると、それ自体が漏洩になる。
 */
class ActivityController {
    private readonly ChreeSession $session;
    private readonly AuditLog $audit;

    public function __construct(ChreeSession $session, AuditLog $audit) {
        $this->session = $session;
        $this->audit = $audit;
    }

    /**
     * @return Response|RedirectResponse
     */
    public function index(): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        return Inertia::render('Settings/Activity', [
            'events' => $this->audit->listFor($accountId),
            'keepDays' => AuditLog::KEEP_DAYS,
        ]);
    }
}
