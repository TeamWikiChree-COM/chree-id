<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Application\DeliverServiceDeletions;
use App\Modules\Linking\Application\RequestServiceDeletion;
use App\Modules\Linking\Application\SplitException;
use App\Modules\Linking\Application\SplitServiceAccount;
use App\Modules\Linking\Infrastructure\ServiceDeletionRequestModel;
use App\Modules\Provider\Application\RevokeAccessTokens;
use Illuminate\Support\Facades\DB;

/**
 * 本人の申し出で ChreeID を退会する。
 *
 * すぐには消さない。押し間違いや乗っ取りから戻せるように、まず退会済みとして
 * 印を付け、猶予を過ぎてから `PurgeDeletedAccounts` が行ごと消す。
 *
 * 印を付けた時点でログインは通らなくなる (`softDelete` が `suspended_at` も立てる)。
 * 連携先から見ても即座に使えなくなるよう、発行済みのトークンはここで失効させる。
 *
 * 連携サービスは、本人の選択に従って解放 (分離) するか、サービスへ削除を依頼する。
 */
class WithdrawAccount {
    private readonly AuthIdentityRepository $accounts;
    private readonly RevokeAccessTokens $tokens;
    private readonly SplitServiceAccount $split;
    private readonly RequestServiceDeletion $deletions;
    private readonly DeliverServiceDeletions $deliver;

    public function __construct(
        AuthIdentityRepository $accounts,
        RevokeAccessTokens $tokens,
        SplitServiceAccount $split,
        RequestServiceDeletion $deletions,
        DeliverServiceDeletions $deliver,
    ) {
        $this->accounts = $accounts;
        $this->tokens = $tokens;
        $this->split = $split;
        $this->deletions = $deletions;
        $this->deliver = $deliver;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param WithdrawalChoices|null $choices 連携サービスごとの選択。運営が退会させるときは null
     * @return bool 退会させたか。見つからない・既に退会済みなら false
     * @throws SplitException 解放できないサービスがあった場合。退会もしない
     */
    public function execute(string $accountId, ?WithdrawalChoices $choices = null): bool {
        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->isDeleted()) return false;

        $choices ??= new WithdrawalChoices();

        $requests = DB::transaction(function () use ($accountId, $account, $choices): array {
            foreach ($choices->releases as $serviceAccountId => $credentialIds) {
                $this->release((string) $serviceAccountId, $accountId, $credentialIds);
            }

            $requests = [];
            foreach ($choices->deletions as $serviceAccountId) {
                $target = $this->split->target($serviceAccountId, $accountId);
                if ($target === null) throw new SplitException(SplitException::NOT_FOUND);

                $requests[] = $this->deletions->execute($target, $account->email);
            }

            // 猶予のあいだ行は残るので、失効させないとトークンが生き続ける
            $this->tokens->forAccount($accountId);

            $this->accounts->softDelete($accountId);

            return $requests;
        });

        // 外部への通信はコミット後に。届かなければ再送に任せる
        foreach ($requests as $request) {
            /** @var ServiceDeletionRequestModel $request */
            $this->deliver->deliver($request);
        }

        return true;
    }

    /**
     * @param string $serviceAccountId 解放するサービスアカウントのID (ULID)
     * @param string $accountId 退会するアカウントのID (ULID)
     * @param list<string> $credentialIds 持っていく認証手段のID
     * @throws SplitException
     */
    private function release(string $serviceAccountId, string $accountId, array $credentialIds): void {
        $target = $this->split->target($serviceAccountId, $accountId);
        if ($target === null) throw new SplitException(SplitException::NOT_FOUND);

        $this->split->execute($target, null, null, $credentialIds);
    }
}
