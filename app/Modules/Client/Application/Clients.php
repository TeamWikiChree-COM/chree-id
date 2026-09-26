<?php
namespace App\Modules\Client\Application;

use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * 登録済みのクライアントを引く・片付ける。
 */
class Clients {
    /**
     * @return list<OAuthClientModel> 名前順
     */
    public function all(): array {
        return array_values(OAuthClientModel::query()->orderBy('name')->get()->all());
    }

    /**
     * @param string $clientId client_id
     * @return OAuthClientModel
     * @throws ModelNotFoundException 無い場合 (画面では 404 になる)
     */
    public function get(string $clientId): OAuthClientModel {
        return OAuthClientModel::query()->findOrFail($clientId);
    }

    /**
     * @param string $clientId client_id
     * @return void
     * @throws ModelNotFoundException 無い場合
     */
    public function delete(string $clientId): void {
        $this->get($clientId)->delete();
    }

    /**
     * 信頼状態を動かせるのは運営だけ。申請はサービス側の意思表示にすぎない。
     *
     * @param string $clientId client_id
     * @return bool 無ければ false
     */
    public function approve(string $clientId): bool {
        $model = OAuthClientModel::query()->find($clientId);
        if ($model === null) return false;

        $model->forceFill([
            'trust' => ServiceTrust::APPROVED,
            // 承認したら申請は片付ける。残すと一覧にいつまでも「承認待ち」が出る
            'review_requested_at' => null,
        ])->save();

        return true;
    }

    /**
     * @param string $ownerId 持ち主のアカウントID (ULID)
     * @return list<OAuthClientModel> 名前順
     */
    public function ownedBy(string $ownerId): array {
        return array_values(OAuthClientModel::query()->where('owner_id', $ownerId)->orderBy('name')->get()->all());
    }

    /**
     * 必ず持ち主で絞る。client_id だけで引くと、他人のサービスを触れてしまう。
     *
     * @param string $ownerId 持ち主のアカウントID (ULID)
     * @param string $clientId client_id
     * @return OAuthClientModel|null 本人のものでなければ null
     */
    public function owned(string $ownerId, string $clientId): ?OAuthClientModel {
        return OAuthClientModel::query()->where('owner_id', $ownerId)->find($clientId);
    }

    /**
     * @param OAuthClientModel $client 審査を申し込むクライアント
     * @return void
     */
    public function requestReview(OAuthClientModel $client): void {
        $client->forceFill(['review_requested_at' => now()])->save();
    }
}
