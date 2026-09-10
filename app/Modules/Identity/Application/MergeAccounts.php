<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\AuthIdentityAliasModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use Illuminate\Support\Facades\DB;

/**
 * 2つの ChreeID を1つに寄せる。
 *
 * **sub は付け替えない。指す先だけ差し替える** (ARCHITECTURE.md 8.6)。
 * 付け替えると、消える側の sub を保存している全サービスが再紐付けを迫られる。
 * 古いIDを指したまま来る問い合わせのために、転送表 (auth_identity_aliases) を永久に残す。
 *
 * 呼ぶ前に**両側が本人のものだと確かめておくこと**。ここでは確認しない。
 * メールの一致は候補を出す材料であって、実行の根拠にはならない (同 8.7)。
 */
class MergeAccounts {
    public function __construct(private readonly AuthIdentityRepository $accounts) {}

    /**
     * @param string $sourceId 消える側のアカウントID (ULID)
     * @param string $targetId 生き残る側のアカウントID (ULID)
     * @return void
     * @throws MergeException 同じサービスに両方が紐付いている場合
     */
    public function execute(string $sourceId, string $targetId): void {
        if ($sourceId === $targetId) throw new MergeException(MergeException::SAME_ACCOUNT);

        $this->assertNoSharedService($sourceId, $targetId);

        DB::transaction(function () use ($sourceId, $targetId): void {
            ServiceAccountModel::query()
                ->where('auth_identity_id', $sourceId)
                ->update(['auth_identity_id' => $targetId]);

            AuthIdentityAliasModel::create([
                'legacy_id' => $sourceId,
                'current_id' => $targetId,
                'merged_at' => now(),
            ]);

            // 消える側の行は残す。転送表が参照するのと、監査で追えるようにするため。
            // 認証手段もそのまま残るが、停止済みなのでどれも通らない
            $this->accounts->suspend($sourceId);
        });
    }

    /**
     * 同じサービスに両方が紐付いていると、統合後は1人がそのサービスに
     * 複数のサービスアカウントを持つ。sub はサービスアカウント側にあるので
     * データとしては成り立つが、ログイン時にどれとして入るかを選ぶ画面がまだ無い。
     * 通すとそのサービスに入れなくなるので、画面ができるまでは受け付けない。
     *
     * @param string $sourceId 消える側のアカウントID (ULID)
     * @param string $targetId 生き残る側のアカウントID (ULID)
     * @return void
     * @throws MergeException
     */
    private function assertNoSharedService(string $sourceId, string $targetId): void {
        $shared = array_intersect($this->clientIdsOf($sourceId), $this->clientIdsOf($targetId));

        if ($shared !== []) throw new MergeException(MergeException::SAME_SERVICE);
    }

    /**
     *
     * @param string $accountId アカウントID (ULID)
     * @return list<string> そのアカウントが関わっているサービスのID
     */
    private function clientIdsOf(string $accountId): array {
        $ids = [];

        foreach (ServiceAccountModel::query()->where('auth_identity_id', $accountId)->get() as $link) {
            $ids[$link->client_id] = true;
        }

        return array_keys($ids);
    }
}
