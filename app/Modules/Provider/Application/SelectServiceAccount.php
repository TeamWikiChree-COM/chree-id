<?php
namespace App\Modules\Provider\Application;

use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * そのサービスに「どのサービスアカウントとして」入るかを決める。
 *
 * 統合すると1人が同じサービスに複数のサービスアカウントを持ちうる
 * (WikiChree は1アカウント1Wiki)。sub はサービスアカウントの持ち物なので、
 * 認証主体だけでは渡す sub が決まらない。そこで認可のときに選ばせる。
 */
class SelectServiceAccount {
    /**
     * 候補を古い順に返す。
     *
     * @param OAuthClientModel $client 接続先サービス
     * @param string $identityId 認証主体のID (ULID)
     * @return Collection<int, ServiceAccountModel>
     */
    public function candidates(OAuthClientModel $client, string $identityId): Collection {
        return ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('auth_identity_id', $identityId)
            ->orderBy('id')
            ->get();
    }

    /**
     * 決める。候補が複数あって選ばれていなければ選ばせる必要がある。
     *
     * @param OAuthClientModel $client 接続先サービス
     * @param string $identityId 認証主体のID (ULID)
     * @param string|null $chosenId 画面で選ばれたサービスアカウントのID
     * @return ServiceAccountModel
     * @throws AmbiguousServiceAccountException 選ばせる必要がある場合
     */
    public function execute(OAuthClientModel $client, string $identityId, ?string $chosenId = null): ServiceAccountModel {
        $candidates = $this->candidates($client, $identityId);

        if ($chosenId !== null) {
            // 画面から戻ってきた値は信用しない。本人の持ち物かをここで確かめる
            $chosen = $candidates->firstWhere('id', $chosenId);
            if ($chosen !== null) return $chosen;
        }

        if ($candidates->count() > 1) throw new AmbiguousServiceAccountException($client->id, $identityId);

        $existing = $candidates->first();
        if ($existing !== null) return $existing;

        // OIDC でログインしただけの人。サービス側の識別子はまだ分からない
        return ServiceAccountModel::create([
            'client_id' => $client->id,
            'auth_identity_id' => $identityId,
            'service_user_id' => null,
        ]);
    }
}
