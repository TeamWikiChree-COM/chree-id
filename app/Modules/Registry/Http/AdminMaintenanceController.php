<?php
namespace App\Modules\Registry\Http;

use App\Modules\Registry\Application\PruneTokens;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 溜まったものの掃除。
 *
 * 日次の `chreeid:prune-tokens` と同じ処理を呼ぶ。共用サーバで cron を
 * 組めていない間も、ここから手で流せるようにしておくためのもの。
 */
class AdminMaintenanceController {
    public function __construct(private readonly PruneTokens $prune) {}

    /**
     * @return Response
     */
    public function index(): Response {
        $pending = $this->prune->pending();

        return Inertia::render('Admin/Maintenance/Index', [
            'pending' => [
                'registrations' => $pending->registrations,
                'emailChanges' => $pending->emailChanges,
                'expiredTokens' => $pending->expiredTokens,
                'usedTokens' => $pending->usedTokens,
                'total' => $pending->total(),
            ],
            'keepDays' => PruneTokens::DEFAULT_DAYS,
        ]);
    }

    /**
     * @return RedirectResponse
     */
    public function prune(): RedirectResponse {
        $pruned = $this->prune->execute();

        return redirect('/admin/maintenance')->with('prunedTokens', $pruned->total());
    }
}
