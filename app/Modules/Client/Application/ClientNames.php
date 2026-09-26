<?php
namespace App\Modules\Client\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;

/**
 * 利用者に見せるサービス名を引く。
 */
class ClientNames {
    /**
     * client_id は外部キーなので、紐付けがある限り必ず引ける。
     *
     * @param string $clientId client_id
     * @return string 利用者に見せるサービス名
     */
    public function of(string $clientId): string {
        return OAuthClientModel::query()->findOrFail($clientId)->displayName();
    }
}
