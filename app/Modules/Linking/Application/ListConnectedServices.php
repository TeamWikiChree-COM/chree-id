<?php
namespace App\Modules\Linking\Application;

use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 利用者から見た「連携しているサービス」の一覧。
 *
 * サービスアカウントの行がそのまま「そのサービスとの関わり」を表す。
 * 遅延登録で裏に作られたものも含むので、まだ一度も ChreeID の画面を
 * 通っていないサービスもここに出る。利用者から見ればどちらも同じ連携。
 */
class ListConnectedServices {
    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<array{id: string, clientId: string, name: string, trust: string, iconUrl: string|null, serviceUserId: string|null, connectedAt: string|null, hasActiveToken: bool}>
     */
    public function execute(string $accountId): array {
        $subjects = ServiceAccountModel::query()
            ->where('auth_identity_id', $accountId)
            ->orderBy('created_at')
            ->get();

        if ($subjects->isEmpty()) return [];

        $clients = OAuthClientModel::query()
            ->whereIn('id', $subjects->pluck('client_id')->all())
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($subjects as $subject) {
            $client = $clients->get($subject->client_id);

            // 管理画面から消されたサービスの記録が残ることがある。出しても操作できない
            if ($client === null) continue;

            $result[] = [
                'id' => $subject->id,
                'clientId' => $client->id,
                'serviceUserId' => $subject->service_user_id,
                'name' => $client->displayName(),
                'iconUrl' => $client->icon_url,
                'trust' => $client->trust->value,
                'connectedAt' => $subject->created_at?->toDateTimeString(),
                'hasActiveToken' => $this->hasActiveToken($accountId, $client->id),
            ];
        }

        return $result;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $clientId サービスの client_id
     * @return bool 有効なアクセストークンが残っているか
     */
    private function hasActiveToken(string $accountId, string $clientId): bool {
        return AccessTokenModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
