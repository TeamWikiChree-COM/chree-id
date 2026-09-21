<?php
namespace App\Modules\Registry\Http;

use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Domain\AdminAccess;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 管理画面に出すアカウントの形を作る。一覧と詳細で同じ形を使う。
 */
class AdminAccountPresenter {
    private readonly AdminAccess $adminAccess;

    public function __construct(AdminAccess $adminAccess) {
        $this->adminAccess = $adminAccess;
    }

    /**
     * @param list<AuthIdentityModel> $models 対象のアカウント
     * @return list<array<string, mixed>>
     */
    public function present(array $models): array {
        $ids = array_map(fn (AuthIdentityModel $m): string => $m->id, $models);
        $types = $this->credentialTypes($ids);
        $services = $this->services($ids);

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
     * 消されたクライアントの行が残ることがあるので、名前が引けなければIDを出す。
     *
     * @param list<string> $clientIds
     * @return array<string, string> client_id => 表示名
     */
    public function clientNames(array $clientIds): array {
        $names = OAuthClientModel::query()
            ->whereIn('id', array_values(array_unique($clientIds)))
            ->get()
            ->mapWithKeys(fn (OAuthClientModel $c): array => [$c->id => $c->displayName()])
            ->all();

        $result = [];
        foreach ($clientIds as $id) {
            $name = $names[$id] ?? null;
            $result[$id] = is_string($name) ? $name : $id;
        }

        return $result;
    }

    /**
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
     * いま何に紐付いているかは ServiceAccount の行を見る。
     * 1人が同じサービスに複数持てるので、サービス名だけでなく識別子も出す。
     *
     * @param list<string> $accountIds 対象のアカウントID
     * @return array<string, list<array{clientId: string, name: string, serviceUserId: string|null}>>
     */
    private function services(array $accountIds): array {
        if ($accountIds === []) return [];

        $links = ServiceAccountModel::query()
            ->whereIn('auth_identity_id', $accountIds)
            ->orderBy('created_at')
            ->get();

        $names = $this->clientNames(array_values($links->map(fn (ServiceAccountModel $link): string => $link->client_id)->all()));
        $services = [];

        foreach ($links as $link) {
            $services[$link->auth_identity_id][] = [
                'clientId' => $link->client_id,
                'name' => $names[$link->client_id],
                'serviceUserId' => $link->service_user_id,
            ];
        }

        return $services;
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
