<?php
namespace App\Modules\Linking\Application;

use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Provider\Infrastructure\ServiceSubjectIdModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 利用者から見た「連携しているサービス」の一覧。
 *
 * service_subject_ids はサービスごとの sub を採番した記録で、
 * 初めてトークンを出した時点で作られる。つまり「一度でも繋がった」証跡になる。
 */
class ListConnectedServices {
    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<array{clientId: string, name: string, trust: string, connectedAt: string|null, hasActiveToken: bool}>
     */
    public function execute(string $accountId): array {
        $subjects = ServiceSubjectIdModel::query()
            ->where('chree_account_id', $accountId)
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
                'clientId' => $client->id,
                'name' => $client->name,
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
            ->where('chree_account_id', $accountId)
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
