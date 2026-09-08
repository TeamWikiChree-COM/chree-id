<?php
namespace App\Modules\Credential\Application;

use RuntimeException;

/**
 * パスワード再設定リンクが使えなかったときの例外。
 *
 * 無効・期限切れ・使用済みを区別しない。区別して伝えると、
 * 有効なトークンの存在を外から確かめる手がかりになるため。
 */
class PasswordResetTokenException extends RuntimeException {
    public function __construct() {
        parent::__construct('パスワード再設定トークンが使えません');
    }
}
