<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\AccountEmailModel;
use App\Modules\Identity\Mail\VerifyAccountEmailMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * 追加のメールアドレスを登録し、確認メールを送る。
 *
 * **確認が済むまでどこにも使わない。** 打ち間違えたアドレスや他人のアドレスを
 * サービスへ確認済みとして渡すと、そのサービス上で他人になりすませてしまう。
 *
 * 他のアカウントが主アドレスにしているアドレスでも登録は止めない。
 * 確認メールが届く時点で、受信箱はこの人のものだと分かる。
 */
class AddAccountEmail {
    /** リンクの有効分数 */
    public const EXPIRES_MINUTES = 60;

    private readonly AuthIdentityRepository $accounts;
    private readonly UserAccounts $userAccounts;

    public function __construct(AuthIdentityRepository $accounts, UserAccounts $userAccounts) {
        $this->accounts = $accounts;
        $this->userAccounts = $userAccounts;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $email 追加するアドレス
     * @return void
     * @throws AccountEmailException
     */
    public function execute(string $accountId, string $email): void {
        if (!$this->userAccounts->exists($accountId)) throw new AccountEmailException(AccountEmailException::NOT_USER_ACCOUNT);
        if ($this->isRegistered($accountId, $email)) throw new AccountEmailException(AccountEmailException::ALREADY_REGISTERED);

        $row = AccountEmailModel::create(['auth_identity_id' => $accountId, 'email' => $email]);
        $this->send($row);
    }

    /**
     * 確認メールを送り直す。前に送ったリンクは使えなくなる。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $emailId 追加アドレスのID (ULID)
     * @return void
     * @throws AccountEmailException
     */
    public function resend(string $accountId, string $emailId): void {
        $row = AccountEmailModel::query()->where('auth_identity_id', $accountId)->find($emailId);
        if ($row === null) throw new AccountEmailException(AccountEmailException::NOT_FOUND);
        if ($row->isVerified()) return;

        $this->send($row);
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $email アドレス
     * @return bool 主アドレスか追加アドレスとして既にあるか
     */
    private function isRegistered(string $accountId, string $email): bool {
        $primary = $this->accounts->findById($accountId)?->email;
        if ($primary !== null && strcasecmp($primary, $email) === 0) return true;

        return AccountEmailModel::query()
            ->where('auth_identity_id', $accountId)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->exists();
    }

    /**
     * @param AccountEmailModel $row 確認待ちの行
     * @return void
     */
    private function send(AccountEmailModel $row): void {
        $token = Str::random(64);

        $row->forceFill([
            'token_hash' => hash('sha256', $token),
            'token_expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
        ])->save();

        Mail::to($row->email)->send(
            new VerifyAccountEmailMail(url("/profile/emails/verify/{$token}"), self::EXPIRES_MINUTES),
        );
    }
}
