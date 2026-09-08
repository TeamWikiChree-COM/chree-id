<?php
namespace App\Modules\Identity\Application;

use App\Modules\Credential\Application\EnableMagicLink;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccount;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\PendingRegistrationModel;
use Illuminate\Support\Facades\DB;

/**
 * 確認メールのリンクを受けてアカウントを作る。
 *
 * ここまで来た時点でメールアドレスの到達性は確認できているので、
 * email_verified_at を立てた状態で作る。
 * パスワードはこの時点で初めて受け取る。申し込み時に預かると、
 * 第三者が決めた値のままアカウントが作られてしまうため。
 */
class CompleteRegistration {
    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly SetPassword $setPassword,
        private readonly EnableMagicLink $enableMagicLink,
    ) {}

    /**
     * リンクがまだ使えるか調べる。
     *
     * パスワードを打たせてから期限切れを伝えるのを避けるため、画面を出す前に確かめる。
     *
     * @param string $token メールに載せた平文トークン
     * @return bool
     */
    public function isUsable(string $token): bool {
        $pending = $this->find($token);

        return $pending !== null && $this->accounts->findByEmail($pending->email) === null;
    }

    /**
     * @param string $token メールに載せた平文トークン
     * @param string $password 平文パスワード
     * @param string|null $displayName 表示名。未入力なら null
     * @return ChreeAccount
     * @throws RegistrationTokenException|\Throwable トークンが無効・期限切れ、または先にアドレスが使われた場合
     */
    public function execute(string $token, string $password, ?string $displayName = null): ChreeAccount {
        $pending = $this->find($token);

        if ($pending === null) throw RegistrationTokenException::notFound();

        // 申し込みから確認までの間に、同じアドレスが Google 連携などで先に使われている可能性がある
        if ($this->accounts->findByEmail($pending->email) !== null) {
            $pending->delete();

            throw RegistrationTokenException::emailTaken();
        }

        $hash = $this->setPassword->hash($password);

        return DB::transaction(fn (): ChreeAccount => $this->create($pending, $hash, $displayName));
    }

    /**
     * @param string $token メールに載せた平文トークン
     * @return PendingRegistrationModel|null まだ使える申し込み。無ければ null
     */
    private function find(string $token): ?PendingRegistrationModel {
        $pending = PendingRegistrationModel::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($pending === null || !$pending->isUsable()) return null;

        return $pending;
    }

    /**
     * @param PendingRegistrationModel $pending 検証済みの申し込み
     * @param string $passwordHash SetPassword::hash() が返したハッシュ
     * @param string|null $displayName 表示名
     * @return ChreeAccount
     */
    private function create(PendingRegistrationModel $pending, string $passwordHash, ?string $displayName): ChreeAccount {
        $account = $this->accounts->create(AccountOrigin::USER, $pending->email, $displayName);

        $this->setPassword->executeHashed($account->id, $passwordHash);
        $this->accounts->markEmailVerified($account->id);

        // ここまで来た時点でメールは届いているので、メールログインも使えるようにしておく
        $this->enableMagicLink->execute($account->id);

        // 使い切りなので消す。used_at を残すより、申し込みが溜まらない方を取る
        $pending->delete();

        return $account;
    }
}
