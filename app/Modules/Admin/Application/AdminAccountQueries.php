<?php
namespace App\Modules\Admin\Application;

use App\Modules\Client\Application\Clients;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;

/**
 * 管理画面でアカウントまわりを見るための読み取り。
 */
class AdminAccountQueries {
    private readonly Clients $clients;

    public function __construct(Clients $clients) {
        $this->clients = $clients;
    }

    /**
     * @return array{clients: int, accounts: int, suspended: int}
     */
    public function stats(): array {
        return [
            'clients' => OAuthClientModel::query()->count(),
            'accounts' => AuthIdentityModel::query()->whereNull('deleted_at')->count(),
            'suspended' => AuthIdentityModel::query()->whereNotNull('suspended_at')->count(),
        ];
    }

    /**
     * @return list<array{id: string, name: string}> 絞り込みに出すサービス。名前順
     */
    public function clientOptions(): array {
        return array_map(
            static fn (OAuthClientModel $c): array => ['id' => $c->id, 'name' => $c->displayName()],
            $this->clients->all(),
        );
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return AuthIdentityModel|null
     */
    public function find(string $accountId): ?AuthIdentityModel {
        return AuthIdentityModel::query()->find($accountId);
    }

    /**
     * @param list<string> $accountIds アカウントID
     * @return list<AuthIdentityModel> 新しい順
     */
    public function findMany(array $accountIds): array {
        return array_values(AuthIdentityModel::query()->whereIn('id', $accountIds)->orderByDesc('created_at')->get()->all());
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<ServiceAccountModel> 古い順
     */
    public function serviceAccounts(string $accountId): array {
        return array_values(ServiceAccountModel::query()->where('auth_identity_id', $accountId)->orderBy('created_at')->get()->all());
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
    public function credentialTypes(array $accountIds): array {
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
    public function services(array $accountIds): array {
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
}
