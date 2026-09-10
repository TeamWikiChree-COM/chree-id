<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use RuntimeException;

/**
 * マジックリンクのトークンを発行する。
 *
 * メールが登録されているだけで送れてしまうと「credentials が0件 = ログインする材料が無い」
 * が崩れるため、有効化されていない場合は発行しない。
 */
class IssueMagicLink {
    /** 発行から失効までの分数 */
    private const EXPIRES_MINUTES = 15;

    public function __construct(private readonly IssueOneTimeToken $issue) {}

    /**
     * @param string $accountId アカウントID (ULID)
     * @return string メールに載せる平文トークン
     * @throws RuntimeException メールログインが有効になっていない場合
     */
    public function execute(string $accountId): string {
        if (!$this->isEnabled($accountId)) {
            throw new RuntimeException("メールログインが有効になっていません: {$accountId}");
        }

        return $this->issue->execute($accountId, OneTimeTokenModel::PURPOSE_LOGIN, self::EXPIRES_MINUTES);
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return bool
     */
    private function isEnabled(string $accountId): bool {
        return CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::MAGIC_LINK)
            ->exists();
    }
}
