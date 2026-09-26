<?php
namespace App\Modules\Linking\Application;

use App\Modules\Identity\Infrastructure\UserAccountModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;

/**
 * サービスごとのサービスアカウントの数。移行元へ落とす経路をいつ消せるかの目安になる。
 */
class ServiceAccountCounts {
    /**
     * @param string $clientId サービスの client_id
     * @return int このサービスのサービスアカウント数
     */
    public function total(string $clientId): int {
        return ServiceAccountModel::query()->where('client_id', $clientId)->count();
    }

    /**
     * 束ねる人格を持つに至った数。移行がどこまで進んでいるかの目安。
     *
     * @param string $clientId サービスの client_id
     * @return int
     */
    public function migrated(string $clientId): int {
        return ServiceAccountModel::query()
            ->where('client_id', $clientId)
            ->whereIn('auth_identity_id', UserAccountModel::query()->select('auth_identity_id'))
            ->count();
    }
}
