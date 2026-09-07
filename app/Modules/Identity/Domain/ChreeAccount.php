<?php
namespace App\Modules\Identity\Domain;

use Illuminate\Support\Carbon;

/**
 * ChreeID アカウント。
 *
 * Eloquent を知らない素の値として扱う。永続化は ChreeAccountRepository の担当。
 */
readonly class ChreeAccount {
    public string $id;
    public ?string $email;
    public ?Carbon $emailVerifiedAt;
    public ?string $displayName;
    public AccountOrigin $origin;
    public ?Carbon $suspendedAt;

    /**
     * @param string $id アカウントID (ULID)
     * @param string|null $email 連絡先
     * @param Carbon|null $emailVerifiedAt メール検証済み日時
     * @param string|null $displayName 表示名
     * @param AccountOrigin $origin 発行経路
     * @param Carbon|null $suspendedAt 停止日時
     */
    public function __construct(
        string $id,
        ?string $email,
        ?Carbon $emailVerifiedAt,
        ?string $displayName,
        AccountOrigin $origin,
        ?Carbon $suspendedAt,
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->displayName = $displayName;
        $this->origin = $origin;
        $this->suspendedAt = $suspendedAt;
    }

    /** @return bool */
    public function isEmailVerified(): bool {
        return $this->emailVerifiedAt !== null;
    }

    /** @return bool */
    public function isSuspended(): bool {
        return $this->suspendedAt !== null;
    }
}
