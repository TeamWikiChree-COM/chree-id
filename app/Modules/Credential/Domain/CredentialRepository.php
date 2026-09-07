<?php
namespace App\Modules\Credential\Domain;

/**
 * 認証手段の問い合わせ。
 *
 * Verifier は Infrastructure なので直接 Eloquent を触ってよいが、
 * Application から使う判定はこのインターフェース越しにする。
 */
interface CredentialRepository {
    /**
     * 認証手段を1つでも持っているか。
     *
     * 0件なら未引き取りで、ChreeID に直接ログインする材料が無い。
     *
     * @param string $accountId アカウントID (ULID)
     * @return bool
     */
    public function hasAny(string $accountId): bool;

    /**
     * 2要素目を必須とするアカウントか。
     *
     * @param string $accountId アカウントID (ULID)
     * @return bool
     */
    public function requiresSecondFactor(string $accountId): bool;

    /**
     * @param string $accountId アカウントID (ULID)
     * @param CredentialType $type 調べる認証方式
     * @return bool
     */
    public function has(string $accountId, CredentialType $type): bool;
}
