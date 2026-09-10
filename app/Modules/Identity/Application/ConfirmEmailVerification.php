<?php
namespace App\Modules\Identity\Application;

use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Domain\AuthIdentityRepository;

/**
 * 確認リンクを受けて、メールアドレスを検証済みにする。
 */
class ConfirmEmailVerification {
    public function __construct(private readonly AuthIdentityRepository $accounts) {}

    /**
     * @param string $token メールに載せた平文トークン
     * @return string|null 検証したアカウントID。使えないリンクなら null
     */
    public function execute(string $token): ?string {
        $row = OneTimeTokenModel::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('purpose', OneTimeTokenModel::PURPOSE_VERIFY_EMAIL)
            ->first();

        if ($row === null || !$row->isUsable()) return null;

        // 使用済みにする。削除しないのは、同じリンクの二重投入を検知できるようにするため
        $row->forceFill(['used_at' => now()])->save();

        $this->accounts->markEmailVerified($row->auth_identity_id);

        return $row->auth_identity_id;
    }
}
