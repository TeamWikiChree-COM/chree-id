<?php
namespace App\Modules\Registry\Http;

use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Registry\Application\ManageAccount;
use App\Modules\Registry\Domain\AdminAccess;
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
class AdminAccountController {
    public function __construct(
        private readonly AdminAccess $adminAccess,
        private readonly ManageAccount $manage,
        private readonly ChreeSession $session,
    ) {}

    /**
     * @return Response
     */
    public function index(): Response {
        $models = AuthIdentityModel::query()
            ->orderByDesc('created_at')
            // 同じ秒に作られた分の並びが揺れないよう、ULID で決着を付ける
            ->orderByDesc('id')
            ->get()
            ->all();

        $types = $this->credentialTypes(array_values(array_map(
            fn (AuthIdentityModel $m): string => $m->id,
            $models,
        )));

        $accounts = array_values(array_map(fn (AuthIdentityModel $m): array => [
            'id' => $m->id,
            'email' => $m->email,
            'displayName' => $m->display_name,
            'origin' => $m->origin->value,
            'isEmailVerified' => $m->email_verified_at !== null,
            'isSuspended' => $m->suspended_at !== null,
            'isDeleted' => $m->deleted_at !== null,
            'deletedAt' => $m->deleted_at?->format('Y/m/d H:i'),
            'isAdmin' => $this->isAdmin($m),
            'createdAt' => $m->created_at?->format('Y/m/d H:i') ?? '',
            'credentialTypes' => $types[$m->id] ?? [],
        ], $models));

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => $accounts,
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
            'action' => ['required', 'string', 'in:suspend,unsuspend,withdraw,restore,purge'],
        ]);

        $actor = $this->actor();
        $action = $request->string('action')->toString();

        return $this->run(fn () => match ($action) {
            'suspend' => $this->manage->suspend($actor, $account),
            'unsuspend' => $this->manage->unsuspend($actor, $account),
            'withdraw' => $this->manage->withdraw($actor, $account),
            'restore' => $this->manage->restore($actor, $account),
            'purge' => $this->manage->purge($actor, $account),
            // validate が in: で絞っているが、そちらを足してここを忘れると黙って何もしない
            default => throw new RuntimeException('不明な操作です'),
        });
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

        return redirect('/admin/accounts');
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
     * アカウントごとの認証手段の種類。
     *
     * @param list<string> $accountIds 対象のアカウントID
     * @return array<string, list<string>> アカウントID => 種類の値
     */
    private function credentialTypes(array $accountIds): array {
        if ($accountIds === []) return [];

        $types = [];

        foreach (CredentialModel::query()->whereIn('auth_identity_id', $accountIds)->get() as $credential) {
            $types[$credential->auth_identity_id][$credential->type->value] = true;
        }

        return array_map(fn (array $set): array => array_keys($set), $types);
    }

    /**
     * @param AuthIdentityModel $account 判定するアカウント
     * @return bool
     */
    private function isAdmin(AuthIdentityModel $account): bool {
        // 未検証のアドレスで名乗れると、管理者のアドレスを先に登録するだけで管理者に見えてしまう
        if ($account->email === null || $account->email_verified_at === null) return false;

        return $this->adminAccess->allowsEmail($account->email);
    }
}
