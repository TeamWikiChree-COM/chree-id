<?php
namespace App\Modules\Linking\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 移行元でアカウントが消えたときに、対応する ChreeID アカウントを止める。
 *
 * 物理削除はしない。引き取り済みでも本人が ChreeID 単体で使い続けている
 * 場合があるため、行は残したままログインできなくする (ソフトデリート)。
 */
class DeactivateServiceAccount {
    public function __construct(private readonly AuthIdentityRepository $accounts) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return bool 対象が見つかり停止できたか
     */
    public function execute(OAuthClientModel $client, string $serviceUserId): bool {
        $link = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        if ($link === null) return false;

        $this->accounts->suspend($link->auth_identity_id);

        return true;
    }
}
