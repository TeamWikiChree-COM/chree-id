<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Domain\PlusAddress;
use App\Modules\Identity\Infrastructure\AccountEmailModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;

/**
 * サービスごとに渡すメールアドレスの割り当て。
 *
 * 割り当てられるのは**本人のものと確かめたアドレス**だけ。サービスには確認済みとして渡るので、
 * 確かめていないアドレスを許すと、そのサービス上で他人になりすませてしまう。
 * 「+」付き版は、同じ受信箱に届くと分かっているドメインに限って確かめ済みとみなす。
 *
 * 割り当てが無い (null) サービスには主アドレスを渡す。同じアドレスを複数のサービスへ割り当ててよい。
 */
class ServiceEmails {
    private readonly AuthIdentityRepository $accounts;
    private readonly UserAccounts $userAccounts;
    private readonly PlusAddress $plus;

    public function __construct(AuthIdentityRepository $accounts, UserAccounts $userAccounts, PlusAddress $plus) {
        $this->accounts = $accounts;
        $this->userAccounts = $userAccounts;
        $this->plus = $plus;
    }

    /**
     * 画面で選べるアドレス。
     *
     * @param string $accountId アカウントID (ULID)
     * @return list<array{email: string, plus: bool}> plus は「+」付き版を作れるか
     */
    public function options(string $accountId): array {
        return array_map(
            fn (string $email): array => ['email' => $email, 'plus' => $this->plus->supports($email)],
            $this->owned($accountId),
        );
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $serviceAccountId サービスアカウントのID (ULID)
     * @param string|null $email 割り当てるアドレス。null なら主アドレスに戻す
     * @return void
     * @throws AccountEmailException
     */
    public function assign(string $accountId, string $serviceAccountId, ?string $email): void {
        if (!$this->userAccounts->exists($accountId)) throw new AccountEmailException(AccountEmailException::NOT_USER_ACCOUNT);

        $link = ServiceAccountModel::query()->where('auth_identity_id', $accountId)->find($serviceAccountId);
        if ($link === null) throw new AccountEmailException(AccountEmailException::NOT_FOUND);
        if ($email !== null && !$this->isAssignable($accountId, $email)) {
            throw new AccountEmailException(AccountEmailException::NOT_ASSIGNABLE);
        }

        $link->forceFill(['email' => $email])->save();
    }

    /**
     * 使えなくなった割り当てを主アドレスに戻す。アドレスを消した・差し替えた後に呼ぶ。
     *
     * 残すと、本人がもう受け取れないアドレスを確認済みとしてサービスへ渡し続けてしまう。
     *
     * @param string $accountId アカウントID (ULID)
     * @return void
     */
    public function prune(string $accountId): void {
        $links = ServiceAccountModel::query()
            ->where('auth_identity_id', $accountId)
            ->whereNotNull('email')
            ->get();

        foreach ($links as $link) {
            if (!$this->isAssignable($accountId, (string) $link->email)) $link->forceFill(['email' => null])->save();
        }
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $email 割り当てたいアドレス
     * @return bool
     */
    private function isAssignable(string $accountId, string $email): bool {
        foreach ($this->owned($accountId) as $owned) {
            if (strcasecmp($owned, $email) === 0) return true;
            if ($this->plus->supports($email) && $this->plus->sameInbox($email, $owned)) return true;
        }

        return false;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<string> 確認済みの主アドレスと追加アドレス
     */
    private function owned(string $accountId): array {
        $account = $this->accounts->findById($accountId);
        $owned = $account?->email !== null && $account->isEmailVerified() ? [$account->email] : [];

        $extra = AccountEmailModel::query()
            ->where('auth_identity_id', $accountId)
            ->whereNotNull('verified_at')
            ->orderBy('created_at')
            ->pluck('email')
            ->all();

        return array_values(array_merge($owned, $extra));
    }
}
