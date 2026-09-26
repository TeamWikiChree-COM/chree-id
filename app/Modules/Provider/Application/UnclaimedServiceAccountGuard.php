<?php
namespace App\Modules\Provider\Application;

use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Client\Infrastructure\OAuthClientModel;

/**
 * 引き取り前のサービスアカウントを、発行元以外のサービスへ入らせない。
 *
 * サービスアカウントは発行したサービス専用の器。移行元のパスワードを引き継いでいるので
 * ChreeID には直接ログインできてしまうが、そのまま別サービスへ OIDC で入らせると
 * SelectServiceAccount が同じ器に別サービスの行を作り、1サービス1アカウントの前提が崩れる。
 * 複数のサービスを束ねるのはユーザーアカウントの役目なので、先に引き取ってもらう。
 */
class UnclaimedServiceAccountGuard {
    private readonly UserAccounts $userAccounts;

    public function __construct(UserAccounts $userAccounts) {
        $this->userAccounts = $userAccounts;
    }

    /**
     * @param OAuthClientModel $client 接続先サービス
     * @param string $identityId 認証主体のID (ULID)
     * @return bool 止めるべきか
     */
    public function blocks(OAuthClientModel $client, string $identityId): bool {
        if ($this->userAccounts->exists($identityId)) return false;

        // 発行元への再ログインは正当な使い方
        return !ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('auth_identity_id', $identityId)
            ->exists();
    }
}
