<?php
namespace App\Modules\Admin\Http;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Application\AdminAccountQueries;
use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Admin\Application\ManageAccount;
use App\Modules\Admin\Application\SearchAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * 管理画面のアカウント一覧。
 *
 * 登録済みアカウントの利用状況（検証状態、認証設定、停止状態など）を一覧表示する。
 */
class AdminAccountController extends Controller {
    private readonly ManageAccount $manage;
    private readonly SearchAccounts $search;
    private readonly AdminAccountPresenter $presenter;
    private readonly ChreeSession $session;
    private readonly AuditLog $audit;
    private readonly AdminAccountQueries $queries;

    public function __construct(
        ManageAccount $manage,
        SearchAccounts $search,
        AdminAccountPresenter $presenter,
        ChreeSession $session,
        AuditLog $audit,
        AdminAccountQueries $queries,
    ) {
        $this->manage = $manage;
        $this->search = $search;
        $this->presenter = $presenter;
        $this->session = $session;
        $this->audit = $audit;
        $this->queries = $queries;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response {
        $query = $request->string('q')->trim()->toString();
        $kind = $this->choice($request, 'kind', SearchAccounts::KINDS);
        $status = $this->choice($request, 'status', SearchAccounts::STATUSES);
        $client = $request->string('client')->toString();
        $page = $this->search->execute($query, $kind, $status, $client === '' ? null : $client, max(1, $request->integer('page', 1)));

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => $this->presenter->present(array_values($page->items())),
            'pagination' => ['page' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
            'filters' => ['q' => $query, 'kind' => $kind ?? '', 'status' => $status ?? '', 'client' => $client],
            'clients' => $this->queries->clientOptions(),
            // 自分の行では操作ボタンを出さない。締め出されると戻れなくなる
            'selfId' => $this->session->accountId(),
            'graceDays' => PurgeDeletedAccounts::graceDays(),
        ]);
    }

    /**
     * アカウントを作る。認証手段は付かないので、本人に再設定してもらう。
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:100'],
        ]);

        $this->manage->create(
            $request->string('email')->toString(),
            $this->nullableName($request),
        );

        return redirect('/admin/accounts')->with('accountCreated', true);
    }

    /**
     * @param Request $request
     * @param string $account 対象のアカウントID
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function update(Request $request, string $account): RedirectResponse {
        $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:100'],
        ]);

        $email = $request->string('email')->trim()->toString();

        return $this->run(fn () => $this->manage->update(
            $this->actor(),
            $account,
            $this->nullableName($request),
            $email === '' ? null : $email,
        ));
    }

    /**
     * 停止・解除・退会・復帰・物理削除。何をするかは action で決まる。
     *
     * @param Request $request
     * @param string $account 対象のアカウントID
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function act(Request $request, string $account): RedirectResponse {
        $request->validate([
            'action' => ['required', 'string', 'in:suspend,unsuspend,withdraw,restore,purge,promote'],
        ]);

        $actor = $this->actor();
        $action = $request->string('action')->toString();

        $result = $this->run(fn () => match ($action) {
            'suspend' => $this->manage->suspend($actor, $account),
            'unsuspend' => $this->manage->unsuspend($actor, $account),
            'withdraw' => $this->manage->withdraw($actor, $account),
            'restore' => $this->manage->restore($actor, $account),
            'purge' => $this->manage->purge($actor, $account),
            'promote' => $this->manage->promote($actor, $account),
            // validate が in: で絞っているが、そちらを足してここを忘れると黙って何もしない
            default => throw new RuntimeException(__('admin.account.unknown_action')),
        });

        // 物理削除は行ごと消えるので、対象ではなく管理者の記録として残す
        $this->audit->record(
            AuditAction::ADMIN_ACCOUNT_ACTED,
            $action === 'purge' ? null : $account,
            ['action' => $action, 'account' => $account],
            actorId: $actor,
        );

        // 詳細ページで消すと戻り先が無くなる
        return $action === 'purge' ? redirect('/admin/accounts') : $result;
    }

    /**
     * 断られた理由をそのまま画面に出す。1つに潰すと直しかたが分からない。
     *
     * @param callable(): void $operation 実行する操作
     * @return RedirectResponse
     * @throws ValidationException 操作が断られた場合
     */
    private function run(callable $operation): RedirectResponse {
        try {
            $operation();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['account' => $e->getMessage()]);
        }

        // 一覧と詳細のどちらからも呼ばれるので、来た画面へ戻す
        return redirect()->back(302, [], '/admin/accounts');
    }

    /**
     * @return string 操作している管理者のアカウントID
     */
    private function actor(): string {
        return $this->session->accountId() ?? '';
    }

    /**
     * @param Request $request
     * @return string|null 空欄は未設定として扱う
     */
    private function nullableName(Request $request): ?string {
        $name = $request->string('display_name')->trim()->toString();

        return $name === '' ? null : $name;
    }

    /**
     * 決まった値以外は「絞らない」として扱う。
     *
     * @param Request $request
     * @param string $key
     * @param list<string> $allowed
     * @return string|null
     */
    private function choice(Request $request, string $key, array $allowed): ?string {
        $value = $request->string($key)->toString();

        return in_array($value, $allowed, true) ? $value : null;
    }
}
