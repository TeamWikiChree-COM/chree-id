<?php
namespace App\Modules\Linking\Domain;

use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use Illuminate\Support\Collection;

/**
 * 同じサービスに対するサービスアカウントの中から、使う行を絞る。
 *
 * OIDC でログインしただけの行 (service_user_id が無い) と、サービスが発行した行 (service_user_id がある) は、
 * 移行・統合を経ると同じ認証主体に並ぶ。サービスが実際に使っているのは後者なので、
 * 後者があるなら前者は無いものとして扱う。放っておくと、ログインで「どれとして入るか」を聞かれたり、
 * パスワードの照合でサービス側の識別子が空で返って、サービスが本人のログインとみなせなくなる。
 *
 * **行は消さない。** sub はサービスアカウントの持ち物で、消して作り直すと向こうから別人に見える。
 */
final class LinkedServiceAccounts {
    private function __construct() {}

    /**
     * @param Collection<int, ServiceAccountModel> $accounts 同じサービスの行
     * @return Collection<int, ServiceAccountModel>
     */
    public static function prefer(Collection $accounts): Collection {
        $linked = $accounts
            ->filter(static fn (ServiceAccountModel $account): bool => $account->service_user_id !== null)
            ->values();

        return $linked->isEmpty() ? $accounts->values() : $linked;
    }
}
