<?php
namespace App\Modules\Identity\Application;

use App\Modules\Credential\Application\IssueOneTimeToken;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Mail\VerifyEmailMail;
use Illuminate\Support\Facades\Mail;

/**
 * ログイン中のアカウントに、メールアドレス確認のリンクを送る。
 *
 * 登録フローでメール確認を挟むより前に作られたアカウントや、
 * 外部 IdP 側が未検証だったアカウントは検証済みになっていない。
 * その状態を本人が解消するための経路。
 */
class RequestEmailVerification {
    /** リンクの有効分数 */
    public const EXPIRES_MINUTES = 60;

    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly IssueOneTimeToken $issue,
    ) {}

    /**
     * @param string $accountId アカウントID (ULID)
     * @return bool 送ったら true。アドレスが無い・既に検証済みなら false
     */
    public function execute(string $accountId): bool {
        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->email === null) return false;
        if ($account->isEmailVerified()) return false;

        $token = $this->issue->execute(
            $accountId,
            OneTimeTokenModel::PURPOSE_VERIFY_EMAIL,
            self::EXPIRES_MINUTES,
        );

        Mail::to($account->email)->send(
            new VerifyEmailMail(url("/profile/email/verify/{$token}"), self::EXPIRES_MINUTES),
        );

        return true;
    }
}
