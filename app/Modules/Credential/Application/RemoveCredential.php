<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use RuntimeException;

/**
 * 認証手段を削除する
 */
class RemoveCredential {
    /**
     * 最後の1件は削除させない。
     * 消せてしまうと本人がログインできなくなり、引き取り済み判定も崩れる。
     *
     * @param string $accountId アカウントID (ULID)
     * @param CredentialType $type 削除する認証方式
     * @return void
     * @throws RuntimeException 最後の1件を消そうとした場合
     */
    public function execute(string $accountId, CredentialType $type): void {
        $remaining = CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', '!=', $type->value)
            ->exists();

        if (!$remaining) throw new RuntimeException('最後の認証手段は削除できません');

        CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', $type)
            ->delete();
    }
}
