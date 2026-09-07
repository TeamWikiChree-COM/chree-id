<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * マジックリンクのトークンを発行する。
 *
 * 平文を返すのはこの1回だけで、DB にはハッシュしか残らない。
 * メールを送るのは呼び出し側の仕事。
 */
class IssueMagicLink {
    /** 発行から失効までの分数 */
    private const EXPIRES_MINUTES = 15;

    /**
     * @param string $accountId アカウントID (ULID)
     * @return string メールに載せる平文トークン
     * @throws RuntimeException メールログインが有効になっていない場合
     */
    public function execute(string $accountId): string {
        if (!$this->isEnabled($accountId)) {
            throw new RuntimeException("メールログインが有効になっていません: {$accountId}");
        }

        $token = Str::random(64);

        OneTimeTokenModel::create([
            'chree_account_id' => $accountId,
            'token_hash' => hash('sha256', $token),
            'purpose' => OneTimeTokenModel::PURPOSE_LOGIN,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
        ]);

        return $token;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return bool
     */
    private function isEnabled(string $accountId): bool {
        return CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::MAGIC_LINK)
            ->exists();
    }
}
