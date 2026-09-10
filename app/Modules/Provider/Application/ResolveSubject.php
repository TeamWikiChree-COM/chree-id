<?php
namespace App\Modules\Provider\Application;

use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Str;

/**
 * サービスに渡す sub を決める。
 *
 * **sub はアカウントではなくサービスアカウントの持ち物** (DEFINE-v2.md 3.2)。
 * account_id は統合で書き換わるので、そちらに紐付けると統合のたびに
 * サービスから見た識別子が指す先を失う。サービスアカウントの行は統合しても
 * そのまま残るので、指す先 (account_id) だけ差し替えれば sub は不変で済む。
 *
 * 一度発行した sub は変えない。値そのものも意味を持たせない。
 */
class ResolveSubject {
    /**
     * そのサービスにとってのこの人の sub を返す。まだ無ければ採番する。
     *
     * @param OAuthClientModel $client 接続先サービス
     * @param string $accountId アカウントID (ULID)
     * @return string サービスに渡す sub
     * @throws AmbiguousServiceAccountException 同じサービスに複数のサービスアカウントがある場合
     */
    public function execute(OAuthClientModel $client, string $accountId): string {
        $accounts = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('auth_identity_id', $accountId)
            ->orderBy('id')
            ->get();

        // 統合で1人が同じサービスに複数のサービスアカウントを持つことがある。
        // どれとして入るのかはアカウントだけでは決まらないので、選ばせる画面が要る
        if ($accounts->count() > 1) throw new AmbiguousServiceAccountException($client->id, $accountId);

        $existing = $accounts->first();
        if ($existing !== null) return $this->ensureSub($existing);

        // OIDC でログインしただけの人。サービス側の識別子はまだ分からない
        $created = ServiceAccountModel::create([
            'client_id' => $client->id,
            'auth_identity_id' => $accountId,
            'service_user_id' => null,
            'sub' => $this->generate(),
        ]);

        return $created->sub ?? '';
    }

    /**
     * どのサービスアカウントとして入るかが決まっているときはこちら。
     *
     * @param ServiceAccountModel $serviceAccount 対象のサービスアカウント
     * @return string サービスに渡す sub
     */
    public function forServiceAccount(ServiceAccountModel $serviceAccount): string {
        return $this->ensureSub($serviceAccount);
    }

    /**
     * 発行前の行に採番する。M2M で先に作られた行はここを通る。
     *
     * @param ServiceAccountModel $serviceAccount 対象のサービスアカウント
     * @return string
     */
    private function ensureSub(ServiceAccountModel $serviceAccount): string {
        if (is_string($serviceAccount->sub) && $serviceAccount->sub !== '') return $serviceAccount->sub;

        $sub = $this->generate();
        $serviceAccount->forceFill(['sub' => $sub])->save();

        return $sub;
    }

    /**
     * サービスごとに別の値にして、サービスどうしで名寄せできないようにする。
     *
     * **アカウントIDは使わない。** 統合で書き換わるうえ、
     * 内部の識別子を外へ出すことにもなる。
     *
     * @return string
     */
    private function generate(): string {
        return Str::lower(Str::ulid()->toString());
    }
}
