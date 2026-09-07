<?php
namespace App\Modules\Provider\Application;

use App\Modules\Provider\Infrastructure\ServiceSubjectIdModel;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Str;

/**
 * サービスに渡す sub を決める。
 *
 * 一度発行した sub は変えない。アカウント統合しても付け替えず、
 * 指す先だけ差し替えることで、サービス側の改修を不要にする。
 */
class ResolveSubject {
    /**
     * @param OAuthClientModel $client 接続先サービス
     * @param string $accountId アカウントID (ULID)
     * @return string サービスに渡す sub
     */
    public function execute(OAuthClientModel $client, string $accountId): string {
        $existing = ServiceSubjectIdModel::query()
            ->where('client_id', $client->id)
            ->where('chree_account_id', $accountId)
            ->first();

        if ($existing !== null) return $existing->sub;

        $model = ServiceSubjectIdModel::create([
            'client_id' => $client->id,
            'chree_account_id' => $accountId,
            'sub' => $this->generate($client, $accountId),
        ]);

        return $model->sub;
    }

    /**
     * 公式サービスにはアカウントIDをそのまま渡す (public sub)。
     * それ以外はサービスごとに別の値にして、承認済み第三者どうしで名寄せできないようにする。
     *
     * @param OAuthClientModel $client 接続先サービス
     * @param string $accountId アカウントID (ULID)
     * @return string
     */
    private function generate(OAuthClientModel $client, string $accountId): string {
        if ($client->trust === ServiceTrust::OFFICIAL) return $accountId;

        return Str::lower(Str::ulid()->toString());
    }
}
