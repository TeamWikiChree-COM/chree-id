<?php
namespace App\Modules\Admin\Http;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Application\AdminAccountQueries;
use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Credential\Application\ListCredentials;
use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Linking\Application\SplitServiceAccount;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Admin\Application\DetectAccountIssues;
use App\Modules\Admin\Application\ManageAccountLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 管理画面のアカウント詳細。認証手段・サービスアカウント・記録をまとめて見て、その場で直す。
 */
class AdminAccountDetailController extends Controller {
    private readonly AdminAccountPresenter $presenter;
    private readonly ListCredentials $credentials;
    private readonly SplitServiceAccount $split;
    private readonly ManageAccountLinks $links;
    private readonly DetectAccountIssues $issues;
    private readonly AuditLog $audit;
    private readonly ChreeSession $session;
    private readonly AdminAccountQueries $queries;

    public function __construct(AdminAccountPresenter $presenter, ListCredentials $credentials, SplitServiceAccount $split, ManageAccountLinks $links, DetectAccountIssues $issues, AuditLog $audit, ChreeSession $session, AdminAccountQueries $queries) {
        $this->presenter = $presenter;
        $this->credentials = $credentials;
        $this->split = $split;
        $this->links = $links;
        $this->issues = $issues;
        $this->audit = $audit;
        $this->session = $session;
        $this->queries = $queries;
    }

    /**
     * @param string $account 対象のアカウントID
     * @return Response
     */
    public function show(string $account): Response {
        $model = $this->queries->find($account);
        if ($model === null) throw new NotFoundHttpException();

        return Inertia::render('Admin/Accounts/Show', [
            'account' => $this->presenter->present([$model])[0],
            'credentials' => $this->credentials->execute($model->id),
            // パスキーは複製できないので、分離のときに選ばせない
            'splittable' => array_map(fn ($c): string => $c->id, $this->split->options($model->id)),
            'links' => $this->links($model->id),
            'issues' => $this->issues->execute()[$model->id] ?? [],
            'events' => $this->audit->listFor($model->id, false, 100),
            'selfId' => $this->session->accountId(),
            'graceDays' => PurgeDeletedAccounts::graceDays(),
        ]);
    }

    /**
     * @param string $account 対象のアカウントID
     * @param string $credential 消す認証手段のID
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function removeCredential(string $account, string $credential): RedirectResponse {
        return $this->run($account, 'remove_credential', ['credential' => $credential],
            fn () => $this->links->removeCredential($this->actor(), $account, $credential));
    }

    /**
     * @param Request $request
     * @param string $account 対象のアカウントID
     * @param string $link 切り離すサービスアカウントのID
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function split(Request $request, string $account, string $link): RedirectResponse {
        $request->validate(['credentials' => ['array'], 'credentials.*' => ['string']]);

        $credentialIds = array_values(array_filter($request->array('credentials'), is_string(...)));

        return $this->run($account, 'split_service', ['link' => $link],
            fn () => $this->links->split($this->actor(), $account, $link, $credentialIds));
    }

    /**
     * @param string $account 対象のアカウントID
     * @param string $link 消すサービスアカウントのID
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function unlink(string $account, string $link): RedirectResponse {
        return $this->run($account, 'unlink_service', ['link' => $link],
            fn () => $this->links->unlink($this->actor(), $account, $link));
    }

    /**
     * 操作して記録を残す。断られた理由はそのまま画面に出す。
     *
     * @param string $account 対象のアカウントID
     * @param string $action 記録に残す操作名
     * @param array<string, string> $context 記録に添える内容
     * @param callable(): mixed $operation
     * @return RedirectResponse
     * @throws ValidationException
     */
    private function run(string $account, string $action, array $context, callable $operation): RedirectResponse {
        try {
            $operation();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['account' => $e->getMessage()]);
        }

        $this->audit->record(
            AuditAction::ADMIN_ACCOUNT_ACTED,
            $account,
            ['action' => $action, 'account' => $account] + $context,
            actorId: $this->actor(),
        );

        return redirect("/admin/accounts/{$account}");
    }

    /**
     * @param string $accountId 対象のアカウントID
     * @return list<array{id: string, clientId: string, name: string, serviceUserId: string|null, sub: string|null, claimedAt: string|null, createdAt: string|null}>
     */
    private function links(string $accountId): array {
        $rows = $this->queries->serviceAccounts($accountId);
        $names = $this->queries->clientNames(array_map(fn (ServiceAccountModel $row): string => $row->client_id, $rows));

        return array_map(fn (ServiceAccountModel $row): array => [
            'id' => $row->id,
            'clientId' => $row->client_id,
            'name' => $names[$row->client_id],
            'serviceUserId' => $row->service_user_id,
            'sub' => $row->sub,
            'claimedAt' => $row->claimed_at?->toDateTimeString(),
            'createdAt' => $row->created_at?->toDateTimeString(),
        ], $rows);
    }

    /**
     * @return string 操作している管理者のアカウントID
     */
    private function actor(): string {
        return $this->session->accountId() ?? '';
    }
}
