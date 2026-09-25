<?php
namespace App\Modules\Linking\Application;

use App\Modules\Linking\Domain\LinkedServiceAccounts;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Collection;

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
     * @return list<array{id: string, clientId: string, name: string, trust: string, iconUrl: string|null, settingsUrl: string|null, serviceUserId: string|null, email: string|null, connectedAt: string|null, hasActiveToken: bool}>
     */
    public function execute(string $accountId): array {
        // 移行で同じサービスに「ログインだけの行」と「サービスが発行した行」が並ぶと、
        // 同じサービスが2つ出て、解除しても片方が残って見える。使われている方だけを出す
        $subjects = ServiceAccountModel::query()
            ->where('auth_identity_id', $accountId)
            ->orderBy('created_at')
            ->get()
            ->toBase()
            ->groupBy('client_id')
            ->flatMap(static fn (Collection $rows): Collection => LinkedServiceAccounts::prefer($rows))
            ->sortBy('created_at')
            ->values();

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
                // 割り当てたアドレス。null なら主アドレスを渡している
                'email' => $subject->email,
                'name' => $client->displayName(),
                'iconUrl' => $client->icon_url,
                // サービス側の設定画面。指定が無ければ導線を出さない
                'settingsUrl' => $client->settings_url,
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
