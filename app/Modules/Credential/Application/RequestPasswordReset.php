<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Credential\Mail\PasswordResetMail;
use App\Modules\Identity\Application\ResolveByEmail;
use Illuminate\Support\Facades\Mail;

/**
 * パスワード再設定のリンクをメールで送る。
 *
 * 存在しないアドレスでも呼び出し側への戻り値を変えないのは、
 * 画面の応答からアドレスの存在を推測させないため。
 */
class RequestPasswordReset {
    /** リンクの有効分数。ログイン用のマジックリンクより短くする理由は無いので揃える */
    private const EXPIRES_MINUTES = 30;

    public function __construct(
        private readonly ResolveByEmail $byEmail,
        private readonly IssueOneTimeToken $issue,
    ) {}

    /**
     * @param string $email メールアドレス
     * @return void
     */
    public function execute(string $email): void {
        $account = $this->byEmail->primary($email);

        // 停止中のアカウントに再設定させると、停止を回避する手段になりうる
        if ($account === null || $account->isSuspended()) return;

        $token = $this->issue->execute(
            $account->id,
            OneTimeTokenModel::PURPOSE_PASSWORD_RESET,
            self::EXPIRES_MINUTES,
        );

        Mail::to($email)->send(new PasswordResetMail(url("/password/reset/{$token}"), self::EXPIRES_MINUTES));
    }
}
