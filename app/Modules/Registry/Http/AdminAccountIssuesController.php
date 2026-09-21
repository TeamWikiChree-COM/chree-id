<?php
namespace App\Modules\Registry\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Registry\Application\DetectAccountIssues;
use App\Modules\Registry\Application\ManageAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 種別と実体が食い違ったアカウントの一覧。まとめて直せるようにしておく。
 */
class AdminAccountIssuesController {
    public function __construct(
        private readonly DetectAccountIssues $issues,
        private readonly AdminAccountPresenter $presenter,
        private readonly ManageAccount $manage,
        private readonly AuditLog $audit,
        private readonly ChreeSession $session,
    ) {}

    /**
     * @return Response
     */
    public function index(): Response {
        $issues = $this->issues->execute();
        $models = AuthIdentityModel::query()->whereIn('id', array_keys($issues))->orderByDesc('created_at')->get()->all();

        return Inertia::render('Admin/Accounts/Issues', [
            'accounts' => array_map(
                fn (array $row): array => $row + ['issues' => $issues[$row['id']]],
                $this->presenter->present(array_values($models)),
            ),
            'selfId' => $this->session->accountId(),
            'graceDays' => PurgeDeletedAccounts::graceDays(),
        ]);
    }

    /**
     * どの問題も直し方は昇格なので、一括で当てる。
     *
     * @return RedirectResponse
     */
    public function fixAll(): RedirectResponse {
        $actor = $this->session->accountId() ?? '';

        foreach (array_keys($this->issues->execute()) as $id) {
            // 自分の行は操作させない決まり (AdminTarget)。例外で残りが止まらないよう先に外す
            if ($id === $actor) continue;

            $this->manage->promote($actor, $id);
            $this->audit->record(AuditAction::ADMIN_ACCOUNT_ACTED, $id, ['action' => 'promote', 'account' => $id], actorId: $actor);
        }

        return redirect('/admin/accounts/issues');
    }
}
