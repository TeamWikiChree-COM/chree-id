<?php
namespace App\Modules\Registry\Domain;

use App\Modules\Identity\Domain\AuthIdentity;
use Illuminate\Support\Facades\Config;

/**
 * 管理画面に入れるかの判定。
 *
 * 誰が管理者かは .env の CHREEID_ADMIN_EMAILS で決める。
 * アカウントに管理者フラグを持たせないのは、権限の昇格が DB 上の1行の書き換えで
 * 済んでしまう状態を避けるため。設定を変えるには本番のファイルに触る必要がある。
 */
class AdminAccess {
    /**
     * @param AuthIdentity|null $account ログイン中のアカウント
     * @return bool
     */
    public function allows(?AuthIdentity $account): bool {
        if ($account === null || $account->isSuspended()) return false;

        // 未検証のアドレスで名乗れると、管理者のアドレスを先に登録するだけで入れてしまう
        if (!$account->isEmailVerified() || $account->email === null) return false;

        return $this->allowsEmail($account->email);
    }

    /**
     * 管理者として登録されているアドレスか。
     *
     * 到達性や停止状態は見ない。呼び出し側でそこまで確かめること。
     *
     * @param string $email メールアドレス
     * @return bool
     */
    public function allowsEmail(string $email): bool {
        return in_array($this->normalize($email), $this->admins(), true);
    }

    /**
     * @return list<string> 正規化した管理者アドレス
     */
    private function admins(): array {
        /** @var list<string> $emails */
        $emails = Config::array('chreeid.admin_emails');

        return array_map(fn (string $email): string => $this->normalize($email), $emails);
    }

    /**
     * @param string $email メールアドレス
     * @return string 大文字小文字の違いで判定がぶれないようにする
     */
    private function normalize(string $email): string {
        return mb_strtolower(trim($email));
    }
}
