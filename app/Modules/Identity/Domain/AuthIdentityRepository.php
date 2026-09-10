<?php
namespace App\Modules\Identity\Domain;

/**
 * ChreeID アカウントの永続化。
 *
 * Domain 側はこのインターフェースだけを知り、Eloquent の実装は Infrastructure に置く。
 */
interface AuthIdentityRepository {
    /**
     * @param string $id アカウントID (ULID)
     * @return AuthIdentity|null 見つからなければ null
     */
    public function findById(string $id): ?AuthIdentity;

    /**
     * @param string $email メールアドレス
     * @return AuthIdentity|null 見つからなければ null
     */
    public function findByEmail(string $email): ?AuthIdentity;

    /**
     * アカウントを新規発行する。
     *
     * @param AccountOrigin $origin 発行経路
     * @param string|null $email 連絡先。サービス発行では持たないことがある
     * @param string|null $displayName 表示名
     * @return AuthIdentity 発行したアカウント
     */
    public function create(AccountOrigin $origin, ?string $email = null, ?string $displayName = null): AuthIdentity;

    /**
     * メールアドレスの到達性が確認できたことを記録する。
     *
     * @param string $id アカウントID (ULID)
     * @return void
     */
    public function markEmailVerified(string $id): void;

    /**
     * 表示名を差し替える。
     *
     * @param string $id アカウントID (ULID)
     * @param string|null $displayName 未設定に戻す場合は null
     * @return void
     */
    public function updateDisplayName(string $id, ?string $displayName): void;

    /**
     * 発行経路を差し替える。
     *
     * サービスが裏で作ったアカウントを、本人が引き取ったときに user へ移す。
     *
     * @param string $id アカウントID (ULID)
     * @param AccountOrigin $origin 新しい発行経路
     * @return void
     */
    public function changeOrigin(string $id, AccountOrigin $origin): void;

    /**
     * メールアドレスを差し替える。
     *
     * 到達性の確認は呼び出し側の責任。検証済みかどうかはここでは触らない。
     *
     * @param string $id アカウントID (ULID)
     * @param string $email 新しいメールアドレス
     * @return void
     */
    public function updateEmail(string $id, string $email): void;

    /**
     * アカウントを停止する (ソフトデリート)。
     *
     * 行は残したままログイン不能にする。移行元のアカウントが消えた場合など、
     * 物理削除せずに済ませたいときに使う。
     *
     * @param string $id アカウントID (ULID)
     * @return void
     */
    public function suspend(string $id): void;
}
