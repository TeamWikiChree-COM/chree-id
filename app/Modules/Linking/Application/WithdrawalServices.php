<?php
namespace App\Modules\Linking\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;

/**
 * 退会の画面に並べる連携サービス。削除を依頼できるかを添える。
 */
class WithdrawalServices {
    private readonly ListConnectedServices $services;

    private readonly RequestServiceDeletion $deletions;

    public function __construct(ListConnectedServices $services, RequestServiceDeletion $deletions) {
        $this->services = $services;
        $this->deletions = $deletions;
    }

    /**
     * @param string $identityId 認証主体のID (ULID)
     * @return list<array{id: string, clientId: string, name: string, iconUrl: string|null, deletable: bool}>
     */
    public function execute(string $identityId): array {
        $services = $this->services->execute($identityId);
        if ($services === []) return [];

        $serviceAccounts = ServiceAccountModel::query()->whereIn('id', array_column($services, 'id'))->get()->keyBy('id');
        $clients = OAuthClientModel::query()->whereIn('id', array_column($services, 'clientId'))->get()->keyBy('id');

        $result = [];
        foreach ($services as $service) {
            $serviceAccount = $serviceAccounts->get($service['id']);

            $result[] = [
                'id' => $service['id'],
                'clientId' => $service['clientId'],
                'name' => $service['name'],
                'iconUrl' => $service['iconUrl'],
                'deletable' => $serviceAccount !== null && $this->deletions->supports($serviceAccount, $clients->get($service['clientId'])),
            ];
        }

        return $result;
    }
}
