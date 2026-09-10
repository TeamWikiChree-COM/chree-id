<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use Illuminate\Support\Str;

/**
 * メールで送る使い捨てトークンを発行する。
 *
 * 平文を返すのはこの1回だけで、DB にはハッシュしか残らない。
 * メールを送るのは呼び出し側の仕事。
 */
class IssueOneTimeToken {
    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $purpose OneTimeTokenModel::PURPOSE_* のいずれか
     * @param int $ttlMinutes 発行から失効までの分数
     * @return string メールに載せる平文トークン
     */
    public function execute(string $accountId, string $purpose, int $ttlMinutes): string {
        // 同じ用途の未使用トークンは捨てる。最後に送ったリンクだけを有効にする
        OneTimeTokenModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(64);

        OneTimeTokenModel::create([
            'auth_identity_id' => $accountId,
            'token_hash' => hash('sha256', $token),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return $token;
    }
}
