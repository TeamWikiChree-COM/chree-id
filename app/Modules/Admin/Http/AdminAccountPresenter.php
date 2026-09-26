<?php
namespace App\Modules\Admin\Http;

use App\Modules\Admin\Application\AdminAccountQueries;
use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Modules\Admin\Domain\AdminAccess;

/**
 * 管理画面に出すアカウントの形を作る。一覧と詳細で同じ形を使う。
 */
class AdminAccountPresenter {
    private readonly AdminAccess $adminAccess;
    private readonly AdminAccountQueries $queries;

    public function __construct(AdminAccess $adminAccess, AdminAccountQueries $queries) {
        $this->adminAccess = $adminAccess;
        $this->queries = $queries;
    }

    /**
     * @param list<AuthIdentityModel> $models 対象のアカウント
     * @return list<array<string, mixed>>
     */
    public function present(array $models): array {
        $ids = array_map(fn (AuthIdentityModel $m): string => $m->id, $models);
        $types = $this->queries->credentialTypes($ids);
        $services = $this->queries->services($ids);

        return array_map(fn (AuthIdentityModel $m): array => [
            'id' => $m->id,
            'email' => $m->email,
            'displayName' => $m->display_name,
            'origin' => $m->origin->value,
            'isEmailVerified' => $m->email_verified_at !== null,
            'isSuspended' => $m->suspended_at !== null,
            'isDeleted' => $m->deleted_at !== null,
            'deletedAt' => $m->deleted_at?->toDateTimeString(),
            'isAdmin' => $this->isAdmin($m),
            'createdAt' => $m->created_at?->toDateTimeString() ?? '',
            'credentialTypes' => $types[$m->id] ?? [],
            'services' => $services[$m->id] ?? [],
        ], $models);
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
