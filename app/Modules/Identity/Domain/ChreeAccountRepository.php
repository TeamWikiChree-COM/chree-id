<?php
namespace App\Modules\Identity\Domain;

/**
 * ChreeID アカウントの永続化。
 *
 * Domain 側はこのインターフェースだけを知り、Eloquent の実装は Infrastructure に置く。
 */
interface ChreeAccountRepository {
    /**
     * @param string $id アカウントID (ULID)
     * @return ChreeAccount|null 見つからなければ null
     */
    public function findById(string $id): ?ChreeAccount;

    /**
     * @param string $email メールアドレス
     * @return ChreeAccount|null 見つからなければ null
     */
    public function findByEmail(string $email): ?ChreeAccount;

    /**
     * アカウントを新規発行する。
     *
     * @param AccountOrigin $origin 発行経路
     * @param string|null $email 連絡先。サービス発行では持たないことがある
     * @param string|null $displayName 表示名
     * @return ChreeAccount 発行したアカウント
     */
    public function create(AccountOrigin $origin, ?string $email = null, ?string $displayName = null): ChreeAccount;

    /**
     * メールアドレスの到達性が確認できたことを記録する。
     *
     * @param string $id アカウントID (ULID)
     * @return void
     */
    public function markEmailVerified(string $id): void;
}
