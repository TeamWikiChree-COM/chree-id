<?php
namespace App\Modules\Admin\Http;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Application\AdminAccountQueries;
use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Admin\Application\DetectAccountIssues;
use App\Modules\Admin\Application\ManageAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 種別と実体が食い違ったアカウントの一覧。まとめて直せるようにしておく。
 */
class AdminAccountIssuesController extends Controller {
    private readonly DetectAccountIssues $issues;
    private readonly AdminAccountPresenter $presenter;
    private readonly ManageAccount $manage;
    private readonly AuditLog $audit;
    private readonly ChreeSession $session;
    private readonly AdminAccountQueries $queries;

    public function __construct(
        DetectAccountIssues $issues,
        AdminAccountPresenter $presenter,
        ManageAccount $manage,
        AuditLog $audit,
        ChreeSession $session,
        AdminAccountQueries $queries,
    ) {
        $this->issues = $issues;
        $this->presenter = $presenter;
        $this->manage = $manage;
        $this->audit = $audit;
        $this->session = $session;
        $this->queries = $queries;
    }

    /**
     * @return Response
     */
    public function index(): Response {
        $issues = $this->issues->execute();
        $models = $this->queries->findMany(array_keys($issues));

        return Inertia::render('Admin/Accounts/Issues', [
            // present は並びを保つので、同じ位置のモデルから問題を引く
            'accounts' => array_map(
                fn (array $row, AuthIdentityModel $model): array => $row + ['issues' => $issues[$model->id]],
                $this->presenter->present($models),
                $models,
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
