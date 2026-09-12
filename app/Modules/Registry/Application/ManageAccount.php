<?php
namespace App\Modules\Registry\Application;

use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Application\WithdrawAccount;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use RuntimeException;

/**
 * 管理者によるアカウントの操作。
 *
 * **自分自身は操作させない。** 締め出されると管理画面に戻れなくなり、
 * 直すのにサーバへ入る必要が出る。退会したいなら `/settings/withdraw` から。
 *
 * 物理削除だけは猶予を飛ばす。運営が「今すぐ消す」と判断した場合の逃げ道で、
 * 通常は退会させて猶予に任せる。
 */
class ManageAccount {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly UserAccounts $userAccounts,
        private readonly WithdrawAccount $withdraw,
    ) {}

    /**
     * アカウントを作る。**認証手段は付かない。**
     *
     * 本人にパスワード再設定かマジックリンクで入ってもらう前提。
     * ここで管理者がパスワードを決めると、本人以外が知っている状態から始まる。
     *
     * @param string $email 連絡先
     * @param string|null $displayName 表示名
     * @return string アカウントID (ULID)
     */
    public function create(string $email, ?string $displayName): string {
        $account = $this->accounts->create(AccountOrigin::USER, $email, $displayName);

        // 管理者が作るのは本人の ChreeID なので、束ねる人格まで作る
        $this->userAccounts->ensure($account->id);

        return $account->id;
    }

    /**
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $targetId 対象のアカウントID
     * @param string|null $displayName 表示名
     * @param string|null $email 連絡先。null なら変えない
     * @return void
     * @throws RuntimeException 自分自身を操作しようとした場合
     */
    public function update(string $actorId, string $targetId, ?string $displayName, ?string $email): void {
        $target = $this->require($actorId, $targetId);

        $this->accounts->updateDisplayName($targetId, $displayName);

        // アドレスを変えたら、確認は取り直し。管理者が入れた値は届く保証がない
        if ($email !== null && $email !== $target->email) $this->accounts->updateEmail($targetId, $email);
    }

    /**
     * 退会させる (論理削除)。猶予を過ぎたら消える。
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $targetId 対象のアカウントID
     * @return void
     * @throws RuntimeException 自分自身を操作しようとした場合
     */
    public function withdraw(string $actorId, string $targetId): void {
        $this->require($actorId, $targetId);

        $this->withdraw->execute($targetId);
    }

    /**
     * ログインを止める。退会とは別で、猶予も削除も走らない。
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $targetId 対象のアカウントID
     * @return void
     * @throws RuntimeException 自分自身を操作しようとした場合
     */
    public function suspend(string $actorId, string $targetId): void {
        $this->require($actorId, $targetId);

        $this->accounts->suspend($targetId);
    }

    /**
     * 停止を解除する。退会済みのものには使わない (そちらは restore)。
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $targetId 対象のアカウントID
     * @return void
     * @throws RuntimeException 自分自身、または退会済みを対象にした場合
     */
    public function unsuspend(string $actorId, string $targetId): void {
        $target = $this->require($actorId, $targetId);

        // 解除しても deleted_at が残るので、中途半端な状態になる
        if ($target->isDeleted()) {
            throw new RuntimeException(__('admin.account.already_withdrawn'));
        }

        $this->accounts->unsuspend($targetId);
    }

    /**
     * 退会を取り消す。
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $targetId 対象のアカウントID
     * @return void
     * @throws RuntimeException 自分自身を操作しようとした場合
     */
    public function restore(string $actorId, string $targetId): void {
        $this->require($actorId, $targetId);

        $this->accounts->restore($targetId);
    }

    /**
     * 猶予を待たずに消す。**元に戻せない。**
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $targetId 対象のアカウントID
     * @return void
     * @throws RuntimeException 自分自身を操作しようとした場合
     */
    public function purge(string $actorId, string $targetId): void {
        $this->require($actorId, $targetId);

        $this->accounts->delete($targetId);
    }

    /**
     * 対象が居て、かつ自分自身でないことを確かめる。
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $targetId 対象のアカウントID
     * @return AuthIdentity 対象
     * @throws RuntimeException 対象が居ない、または自分自身の場合
     */
    private function require(string $actorId, string $targetId): AuthIdentity {
        if ($actorId === $targetId) {
            throw new RuntimeException(__('admin.account.cannot_operate_self'));
        }

        $target = $this->accounts->findById($targetId);
        if ($target === null) throw new RuntimeException(__('admin.account.not_found'));

        return $target;
    }
}
