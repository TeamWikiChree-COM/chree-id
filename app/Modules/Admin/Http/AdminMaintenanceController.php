<?php
namespace App\Modules\Admin\Http;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditLog;
use App\Modules\Device\Application\LoginSessions;
use App\Modules\Device\Application\TrustedDevices;
use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Admin\Application\PruneTokens;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 溜まったものの掃除。
 *
 * 日次の `chreeid:prune-tokens` と同じ処理を呼ぶ。共用サーバで cron を
 * 組めていない間も、ここから手で流せるようにしておくためのもの。
 */
class AdminMaintenanceController extends Controller {
    private readonly PruneTokens $prune;
    private readonly PurgeDeletedAccounts $purge;
    private readonly LoginSessions $sessions;
    private readonly TrustedDevices $trustedDevices;
    private readonly AuditLog $audit;

    public function __construct(PruneTokens $prune, PurgeDeletedAccounts $purge, LoginSessions $sessions, TrustedDevices $trustedDevices, AuditLog $audit) {
        $this->prune = $prune;
        $this->purge = $purge;
        $this->sessions = $sessions;
        $this->trustedDevices = $trustedDevices;
        $this->audit = $audit;
    }

    /**
     * @return Response
     */
    public function index(): Response {
        $pending = $this->prune->pending();
        $accounts = $this->purge->due();

        return Inertia::render('Admin/Maintenance/Index', [
            'pending' => [
                'registrations' => $pending->registrations,
                'emailChanges' => $pending->emailChanges,
                'expiredTokens' => $pending->expiredTokens,
                'usedTokens' => $pending->usedTokens,
                'withdrawnAccounts' => $accounts,
                'total' => $pending->total() + $accounts,
            ],
            'keepDays' => PruneTokens::DEFAULT_DAYS,
            'graceDays' => PurgeDeletedAccounts::graceDays(),
        ]);
    }

    /**
     * @return RedirectResponse
     */
    public function prune(): RedirectResponse {
        $pruned = $this->prune->execute();

        // 退会は印を付けるだけなので、実際に消えるのはここ
        $accounts = $this->purge->execute();

        // 端末まわりの残骸。実体の無いセッションの控えと、期限切れの信頼
        $devices = $this->sessions->prune() + $this->trustedDevices->prune() + $this->audit->prune();

        return redirect('/admin/maintenance')
            ->with('prunedTokens', $pruned->total() + $accounts + $devices);
    }
}
