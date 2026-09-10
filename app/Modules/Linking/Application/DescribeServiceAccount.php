<?php
namespace App\Modules\Linking\Application;

use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * サービスが自分の利用者の状態を聞きに来る。参照だけで副作用を持たない。
 *
 * サービス側は「移行の導線を出すかどうか」をこれで決められる。
 * 入場券の発行 (`ClaimTickets`) で代用すると、画面を出すたびに券が切り替わってしまう。
 */
class DescribeServiceAccount {
    public function __construct(
        private readonly UserAccounts $userAccounts,
        private readonly ResolveSubject $subjects,
    ) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return array{sub: string, migrated: bool, migrated_at: string|null}|null 未発行なら null
     */
    public function execute(OAuthClientModel $client, string $serviceUserId): ?array {
        $serviceAccount = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        if ($serviceAccount === null) return null;

        return [
            'sub' => $this->subjects->forServiceAccount($serviceAccount),
            // 束ねる人格を持っていれば、本人のものになっている。
            // origin は出自の記録なので、この判定には使わない
            'migrated' => $this->userAccounts->exists($serviceAccount->auth_identity_id),
            'migrated_at' => $serviceAccount->claimed_at?->toIso8601String(),
        ];
    }
}
