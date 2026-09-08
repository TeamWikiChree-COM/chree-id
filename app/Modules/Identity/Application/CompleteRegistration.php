<?php
namespace App\Modules\Identity\Application;

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
 */
class CompleteRegistration {
    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly SetPassword $setPassword,
    ) {}

    /**
     * @param string $token メールに載せた平文トークン
     * @return ChreeAccount
     * @throws RegistrationTokenException トークンが無効・期限切れ、または先にアドレスが使われた場合
     */
    public function execute(string $token): ChreeAccount {
        $pending = PendingRegistrationModel::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($pending === null) throw RegistrationTokenException::notFound();
        if (!$pending->isUsable()) throw RegistrationTokenException::expired();

        // 申し込みから確認までの間に、同じアドレスが Google 連携などで先に使われている可能性がある
        if ($this->accounts->findByEmail($pending->email) !== null) {
            $pending->delete();

            throw RegistrationTokenException::emailTaken();
        }

        return DB::transaction(fn (): ChreeAccount => $this->create($pending));
    }

    /**
     * @param PendingRegistrationModel $pending 検証済みの申し込み
     * @return ChreeAccount
     */
    private function create(PendingRegistrationModel $pending): ChreeAccount {
        $account = $this->accounts->create(
            AccountOrigin::USER,
            $pending->email,
            $pending->display_name,
        );

        $this->setPassword->executeHashed($account->id, $pending->password_hash);
        $this->accounts->markEmailVerified($account->id);

        // 使い切りなので消す。used_at を残すより、申し込みが溜まらない方を取る
        $pending->delete();

        return $account;
    }
}
