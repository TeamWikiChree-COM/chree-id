<?php
namespace App\Modules\Linking\Application;

use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountLinkModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 移行元でアカウントが消えたときに、対応する ChreeID アカウントを止める。
 *
 * 物理削除はしない。引き取り済みでも本人が ChreeID 単体で使い続けている
 * 場合があるため、行は残したままログインできなくする (ソフトデリート)。
 */
class DeactivateServiceAccount {
    public function __construct(private readonly ChreeAccountRepository $accounts) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return bool 対象が見つかり停止できたか
     */
    public function execute(OAuthClientModel $client, string $serviceUserId): bool {
        $link = ServiceAccountLinkModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        if ($link === null) return false;

        $this->accounts->suspend($link->chree_account_id);

        return true;
    }
}
