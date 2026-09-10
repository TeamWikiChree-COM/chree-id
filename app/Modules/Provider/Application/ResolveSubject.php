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
    public function __construct(private readonly SelectServiceAccount $select) {}

    /**
     * そのサービスにとってのこの人の sub を返す。まだ無ければ採番する。
     *
     * @param OAuthClientModel $client 接続先サービス
     * @param string $accountId アカウントID (ULID)
     * @return string サービスに渡す sub
     * @throws AmbiguousServiceAccountException 同じサービスに複数のサービスアカウントがある場合
     */
    public function execute(OAuthClientModel $client, string $accountId): string {
        return $this->ensureSub($this->select->execute($client, $accountId));
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
