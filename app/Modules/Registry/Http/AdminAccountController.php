<?php
namespace App\Modules\Registry\Http;

use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Infrastructure\ChreeAccountModel;
use App\Modules\Registry\Domain\AdminAccess;
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
            // 同じ秒に作られた分の並びが揺れないよう、ULID で決着を付ける
            ->orderByDesc('id')
            ->get()
            ->all();

        $types = $this->credentialTypes(array_values(array_map(
            fn (ChreeAccountModel $m): string => $m->id,
            $models,
        )));

        $accounts = array_values(array_map(fn (ChreeAccountModel $m): array => [
            'id' => $m->id,
            'email' => $m->email,
            'displayName' => $m->display_name,
            'origin' => $m->origin->value,
            'isEmailVerified' => $m->email_verified_at !== null,
            'isSuspended' => $m->suspended_at !== null,
            'isAdmin' => $this->isAdmin($m),
            'createdAt' => $m->created_at?->format('Y/m/d H:i') ?? '',
            'credentialTypes' => $types[$m->id] ?? [],
        ], $models));

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => $accounts,
        ]);
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

        foreach (CredentialModel::query()->whereIn('chree_account_id', $accountIds)->get() as $credential) {
            $types[$credential->chree_account_id][$credential->type->value] = true;
        }

        return array_map(fn (array $set): array => array_keys($set), $types);
    }

    /**
     * @param ChreeAccountModel $account 判定するアカウント
     * @return bool
     */
    private function isAdmin(ChreeAccountModel $account): bool {
        // 未検証のアドレスで名乗れると、管理者のアドレスを先に登録するだけで管理者に見えてしまう
        if ($account->email === null || $account->email_verified_at === null) return false;

        return $this->adminAccess->allowsEmail($account->email);
    }
}
