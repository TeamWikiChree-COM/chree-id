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
     * 種別ごとまとめて削除する。
     *
     * パスキーや外部アカウントのように同じ種別を複数持てるものは、
     * 1件ずつ消せる executeById を使うこと。
     *
     * @param string $accountId アカウントID (ULID)
     * @param CredentialType $type 削除する認証方式
     * @return void
     * @throws RuntimeException 最後の1件を消そうとした場合
     */
    public function execute(string $accountId, CredentialType $type): void {
        $this->assertNotLast(
            CredentialModel::query()
                ->where('auth_identity_id', $accountId)
                ->where('type', $type)
                ->pluck('id')
                ->all(),
            $accountId,
        );

        CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', $type)
            ->delete();
    }

    /**
     * 1件だけ削除する。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $credentialId 削除する認証手段のID (ULID)
     * @return void
     * @throws RuntimeException 見つからない、または最後の1件だった場合
     */
    public function executeById(string $accountId, string $credentialId): void {
        $row = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('id', $credentialId)
            ->first();

        if ($row === null) throw new RuntimeException('その認証手段は見つかりません');

        $this->assertNotLast([$row->id], $accountId);

        $row->delete();
    }

    /**
     * 消したあとにログインできる手段が残るか確かめる。
     *
     * 消せてしまうと本人がログインできなくなり、引き取り済み判定も崩れる。
     *
     * **復旧コードは残りに数えない。** あれは2段階目を通すためのもので、
     * それだけではログインを始められない。
     *
     * @param list<string> $removing これから消す認証手段のID
     * @param string $accountId アカウントID (ULID)
     * @return void
     * @throws RuntimeException 残らない場合
     */
    private function assertNotLast(array $removing, string $accountId): void {
        $remaining = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', '!=', CredentialType::RECOVERY_CODE->value)
            ->whereNotIn('id', $removing)
            ->exists();

        if (!$remaining) throw new RuntimeException('最後の認証手段は削除できません');
    }
}
