<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 再設定リンクを受けてパスワードを差し替える。
 */
class ResetPassword {
    public function __construct(private readonly SetPassword $setPassword) {}

    /**
     * トークンがまだ使えるか調べる。
     *
     * 入力画面を出す前に確かめて、パスワードを打たせてから弾くのを避ける。
     *
     * @param string $token メールに載せた平文トークン
     * @return bool
     */
    public function isUsable(string $token): bool {
        return $this->find($token) !== null;
    }

    /**
     * @param string $token メールに載せた平文トークン
     * @param string $password 新しい平文パスワード
     * @return string 差し替えたアカウントID (ULID)
     * @throws PasswordResetTokenException トークンが無効・期限切れ・使用済みの場合
     * @throws InvalidArgumentException パスワードが短すぎる場合
     */
    public function execute(string $token, string $password): string {
        $row = $this->find($token);
        if ($row === null) throw new PasswordResetTokenException();

        $hash = $this->setPassword->hash($password);

        return DB::transaction(function () use ($row, $hash): string {
            $this->setPassword->executeHashed($row->auth_identity_id, $hash);

            // 使用済みにする。削除しないのは、同じリンクの二重投入を検知できるようにするため
            $row->forceFill(['used_at' => now()])->save();

            return $row->auth_identity_id;
        });
    }

    /**
     * @param string $token メールに載せた平文トークン
     * @return OneTimeTokenModel|null まだ使えるトークンの行。無ければ null
     */
    private function find(string $token): ?OneTimeTokenModel {
        $row = OneTimeTokenModel::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('purpose', OneTimeTokenModel::PURPOSE_PASSWORD_RESET)
            ->first();

        if ($row === null || !$row->isUsable()) return null;

        return $row;
    }
}
