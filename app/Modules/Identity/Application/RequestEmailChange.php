<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\PendingEmailChangeModel;
use App\Modules\Identity\Mail\EmailChangeNoticeMail;
use App\Modules\Identity\Mail\VerifyEmailChangeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * メールアドレスの変更を申し込む。
 *
 * 新しいアドレスに届くことを確かめるまで差し替えない。先に差し替えると、
 * 打ち間違えただけでアカウントに二度と入れなくなる。
 *
 * 古いアドレスにも通知を出す。乗っ取られたときに、本人が気付ける唯一の経路になるため。
 */
class RequestEmailChange {
    /** リンクの有効分数 */
    public const EXPIRES_MINUTES = 60;

    public function __construct(private readonly AuthIdentityRepository $accounts) {}

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $newEmail 新しいメールアドレス
     * @return bool 申し込めたら true。既に他のアカウントが使っていれば false
     */
    public function execute(string $accountId, string $newEmail): bool {
        $account = $this->accounts->findById($accountId);
        if ($account === null) return false;

        // 他人が使っているアドレスには変更できない
        $existing = $this->accounts->findByEmail($newEmail);
        if ($existing !== null && $existing->id !== $accountId) return false;

        $token = $this->issue($accountId, $newEmail);

        Mail::to($newEmail)->send(
            new VerifyEmailChangeMail(url("/profile/email/change/{$token}"), self::EXPIRES_MINUTES),
        );

        // 変更に気付けるよう、今のアドレスにも知らせる
        if ($account->email !== null && $account->email !== $newEmail) {
            Mail::to($account->email)->send(new EmailChangeNoticeMail($newEmail));
        }

        return true;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $newEmail 新しいメールアドレス
     * @return string 平文トークン
     */
    private function issue(string $accountId, string $newEmail): string {
        // 申し込み直しのときは古いリンクを捨てる。最後に送ったものだけを有効にする
        PendingEmailChangeModel::query()->where('auth_identity_id', $accountId)->delete();

        $token = Str::random(64);

        PendingEmailChangeModel::create([
            'auth_identity_id' => $accountId,
            'new_email' => $newEmail,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
        ]);

        return $token;
    }
}
