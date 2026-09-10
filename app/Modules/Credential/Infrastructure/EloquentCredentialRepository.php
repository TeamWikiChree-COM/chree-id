<?php
namespace App\Modules\Credential\Infrastructure;

use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;

/**
 * CredentialRepository の Eloquent 実装
 */
class EloquentCredentialRepository implements CredentialRepository {
    /**
     * @param string $accountId アカウントID (ULID)
     * @return bool
     */
    public function hasAny(string $accountId): bool {
        return CredentialModel::query()->where('auth_identity_id', $accountId)->exists();
    }

    /**
     * TOTP を登録していれば2要素目が必須になる。
     *
     * @param string $accountId アカウントID (ULID)
     * @return bool
     */
    public function requiresSecondFactor(string $accountId): bool {
        return $this->has($accountId, CredentialType::TOTP);
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param CredentialType $type 調べる認証方式
     * @return bool
     */
    public function has(string $accountId, CredentialType $type): bool {
        return CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', $type)
            ->exists();
    }
}
