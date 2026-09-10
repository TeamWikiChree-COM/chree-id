<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Credential\Infrastructure\CredentialModel;
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
     * @param list<string> $credentialIds 寄せ先へ持っていく認証手段のID
     * @return void
     * @throws MergeException 寄せ先と寄せ元が同じ場合
     */
    public function execute(string $sourceId, string $targetId, array $credentialIds = []): void {
        if ($sourceId === $targetId) throw new MergeException(MergeException::SAME_ACCOUNT);

        DB::transaction(function () use ($sourceId, $targetId, $credentialIds): void {
            ServiceAccountModel::query()
                ->where('auth_identity_id', $sourceId)
                ->update(['auth_identity_id' => $targetId]);

            // 選ばれた認証手段だけを移す。何を持っていくかは本人が決める。
            // 残ったものは寄せ元に付いたままだが、停止するのでどれも通らない
            if ($credentialIds !== []) {
                CredentialModel::query()
                    ->where('auth_identity_id', $sourceId)
                    ->whereIn('id', $credentialIds)
                    ->update(['auth_identity_id' => $targetId]);
            }

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

}
