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
     * 同じアドレスの認証主体をすべて返す。
     *
     * **メールは認証主体を一意に決めない。** 同じ人が同一サービスに複数の
     * サービスアカウントを持つとき、そこには普通同じアドレスを使う
     * (ARCHITECTURE.md 8.3)。どれを指すかの判断は `ResolveByEmail` が持つ。
     *
     * @param string $email メールアドレス
     * @return list<AuthIdentity> 古い順
     */
    public function findAllByEmail(string $email): array;

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

    /**
     * アカウントを物理削除する。
     *
     * credentials / service_accounts / user_accounts などは FK の
     * cascade で一律消える。
     *
     * **残すべきものがないと確定してから呼ぶこと。** UserAccount や他の
     * ServiceAccount が残っている認証主体を消すと、他サービスの人格まで
     * 巻き添えにする。
     *
     * @param string $id アカウントID (ULID)
     * @return void
     */
    public function delete(string $id): void;

    /**
     * 退会させる。行は猶予のあいだ残す。
     *
     * **`suspended_at` も同時に立てる。** ログインの可否を見ているのは
     * `isSuspended()` なので、そこに乗せておけば全経路がそのまま遮断する。
     * 退会用の判定を別に足すと、新しい経路で見落とす。
     *
     * @param string $id アカウントID (ULID)
     * @return void
     */
    public function softDelete(string $id): void;

    /**
     * 退会を取り消す。猶予のあいだだけ間に合う。
     *
     * @param string $id アカウントID (ULID)
     * @return void
     */
    public function restore(string $id): void;

    /**
     * 猶予を過ぎた退会済みアカウントを消す。
     *
     * @param int $days 退会から何日残すか
     * @return int 消した件数
     */
    public function purgeDeletedBefore(int $days): int;

    /**
     * 猶予を過ぎて消える対象の件数。消さずに数えるだけ。
     *
     * @param int $days 退会から何日残すか
     * @return int
     */
    public function countDeletedBefore(int $days): int;
}
