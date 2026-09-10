<?php
namespace App\Modules\Linking\Application;

use App\Modules\Identity\Application\MergeAccounts;
use App\Modules\Identity\Application\TransferableCredentials;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;

/**
 * サービスアカウントを、本人が既に持っている ChreeID へ寄せる。
 *
 * 移行 (新しくユーザーアカウントにする) と対になる操作。
 * 利用者から見た入口は同じで、「ChreeID を持っているか」で分かれるだけ。
 *
 * **両側が本人のものだと確かめたうえで呼ぶこと。**
 * サービスの入場券で来ている時点でサービスアカウント側は本人と分かり、
 * その画面で ChreeID にログインしていればユーザーアカウント側も本人と分かる。
 * この2つが揃っていれば、メールアドレスが違っても寄せてよい。
 */
class MergeServiceAccount {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly MergeAccounts $merge,
        private readonly ClaimTickets $tickets,
        private readonly TransferableCredentials $transferable,
        private readonly UserAccounts $userAccounts,
    ) {}

    /**
     * @param ServiceAccountModel $link 寄せるサービスアカウントの紐付け
     * @param string $targetId 寄せ先 (本人が既に持っている) アカウントID (ULID)
     * @param list<string> $credentialIds 持っていく認証手段のID
     * @return void
     * @throws ClaimException
     * @throws \App\Modules\Identity\Application\MergeException
     */
    public function execute(ServiceAccountModel $link, string $targetId, array $credentialIds = []): void {
        $source = $this->accounts->findById($link->auth_identity_id);

        // 既に本人のものになっているアカウントは、サービス経由では触らせない。
        // 許すと、サービスの都合で他人のアカウントを自分の側へ寄せられる
        if ($source === null || $this->userAccounts->exists($source->id)) {
            throw new ClaimException(ClaimException::ALREADY_CLAIMED);
        }

        $target = $this->accounts->findById($targetId);
        if ($target === null || $target->isSuspended()) throw new ClaimException(ClaimException::INVALID_TICKET);

        // 寄せ先は束ねる人格を持つ。まだ無ければここで用意する
        $this->userAccounts->ensure($targetId);

        $this->merge->execute(
            $source->id,
            $targetId,
            $this->transferable->accept($source->id, $targetId, $credentialIds),
        );

        // 統合で紐付けの指す先が変わっているので、書き戻す前に読み直す
        $link->refresh()->forceFill(['claimed_at' => now()])->save();

        $this->tickets->consume($link);
    }
}
