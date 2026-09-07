<?php
namespace App\Modules\Identity\Domain;

use Carbon\CarbonInterface;

/**
 * ChreeID アカウント。
 *
 * Eloquent を知らない素の値として扱う。永続化は ChreeAccountRepository の担当。
 */
readonly class ChreeAccount {
    public string $id;
    public ?string $email;
    public ?CarbonInterface $emailVerifiedAt;
    public ?string $displayName;
    public AccountOrigin $origin;
    public ?CarbonInterface $suspendedAt;

    /**
     * @param string $id アカウントID (ULID)
     * @param string|null $email 連絡先
     * @param CarbonInterface|null $emailVerifiedAt メール検証済み日時
     * @param string|null $displayName 表示名
     * @param AccountOrigin $origin 発行経路
     * @param CarbonInterface|null $suspendedAt 停止日時
     */
    public function __construct(
        string $id,
        ?string $email,
        ?CarbonInterface $emailVerifiedAt,
        ?string $displayName,
        AccountOrigin $origin,
        ?CarbonInterface $suspendedAt,
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
