<?php
namespace App\Modules\Registry\Http;

use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Infrastructure\ChreeAccountModel;
use App\Modules\Registry\Domain\AdminAccess;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理画面のアカウント一覧。
 *
 * 登録済みアカウントの利用状況（検証状態、認証設定、停止状態など）を一覧表示する。
 */
class AdminAccountController {
    public function __construct(
        private readonly AdminAccess $adminAccess,
    ) {}

    /**
     * @return Response
     */
    public function index(): Response {
        $models = ChreeAccountModel::query()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        $credentialsByAccount = $this->loadCredentials($models->pluck('id')->all());

        $accounts = $models->map(fn (ChreeAccountModel $m): array => [
            'id' => $m->id,
            'email' => $m->email,
            'displayName' => $m->display_name,
            'origin' => $m->origin?->value ?? 'user',
            'isEmailVerified' => $m->email_verified_at !== null,
            'isSuspended' => $m->suspended_at !== null,
            'isAdmin' => $this->checkIsAdmin($m),
            'createdAt' => $m->created_at?->format('Y/m/d H:i') ?? '',
            'credentialTypes' => $credentialsByAccount->get($m->id, collect())
                ->pluck('type.value')
                ->unique()
                ->values()
                ->all(),
        ])->all();

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * @param list<string> $accountIds
     * @return Collection<string, Collection<int, CredentialModel>>
     */
    private function loadCredentials(array $accountIds): Collection {
        if ($accountIds === []) return collect();

        return CredentialModel::query()
            ->whereIn('chree_account_id', $accountIds)
            ->get()
            ->groupBy('chree_account_id');
    }

    /**
     * @param ChreeAccountModel $account
     * @return bool
     */
    private function checkIsAdmin(ChreeAccountModel $account): bool {
        if ($account->email === null || $account->email_verified_at === null) return false;

        $emails = config('chreeid.admin_emails', []);
        $normalized = mb_strtolower(trim($account->email));

        return in_array($normalized, array_map('mb_strtolower', $emails), true);
    }
}
