<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Infrastructure\AccountEmailModel;

/**
 * 確認リンクを受けて追加アドレスを確認済みにする。
 *
 * ログインを求めない。別の端末でメールを開くことがあるため。トークン自体が本人の証明になる。
 */
class ConfirmAccountEmail {
    /**
     * @param string $token メールに載せた平文トークン
     * @return string|null 確認したアドレスの持ち主のID。使えないリンクなら null
     */
    public function execute(string $token): ?string {
        $row = AccountEmailModel::query()->where('token_hash', hash('sha256', $token))->first();
        if ($row === null || $row->token_expires_at?->isPast() !== false) return null;

        $row->forceFill(['verified_at' => now(), 'token_hash' => null, 'token_expires_at' => null])->save();

        return $row->auth_identity_id;
    }
}
