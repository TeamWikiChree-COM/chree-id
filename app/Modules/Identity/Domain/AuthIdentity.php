<?php
namespace App\Modules\Identity\Domain;

use Carbon\CarbonInterface;

/**
 * ChreeID アカウント。
 *
 * Eloquent を知らない素の値として扱う。永続化は AuthIdentityRepository の担当。
 */
readonly class AuthIdentity {
    public string $id;
    public ?string $email;
    public ?CarbonInterface $emailVerifiedAt;
    public ?string $displayName;
    public AccountOrigin $origin;
    public ?CarbonInterface $suspendedAt;
    public ?CarbonInterface $deletedAt;
    public IconSource $iconSource;
    public ?string $iconPath;

    /**
     * @param string $id アカウントID (ULID)
     * @param string|null $email 連絡先
     * @param CarbonInterface|null $emailVerifiedAt メール検証済み日時
     * @param string|null $displayName 表示名
     * @param AccountOrigin $origin 発行経路
     * @param CarbonInterface|null $suspendedAt 停止日時
     * @param CarbonInterface|null $deletedAt 退会日時。猶予のあいだ残し、過ぎたら行ごと消す
     * @param IconSource $iconSource アイコンの出どころ
     * @param string|null $iconPath アップロードした画像の保管先。UPLOAD 以外では null
     */
    public function __construct(
        string $id,
        ?string $email,
        ?CarbonInterface $emailVerifiedAt,
        ?string $displayName,
        AccountOrigin $origin,
        ?CarbonInterface $suspendedAt,
        ?CarbonInterface $deletedAt = null,
        IconSource $iconSource = IconSource::NONE,
        ?string $iconPath = null,
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->displayName = $displayName;
        $this->origin = $origin;
        $this->suspendedAt = $suspendedAt;
        $this->deletedAt = $deletedAt;
        $this->iconSource = $iconSource;
        $this->iconPath = $iconPath;
    }

    /** @return bool */
    public function isEmailVerified(): bool {
        return $this->emailVerifiedAt !== null;
    }

    /** @return bool */
    public function isSuspended(): bool {
        return $this->suspendedAt !== null;
    }

    /**
     * 退会済みか。
     *
     * **ログインの可否をここで見ない。** 退会は `suspended_at` も同時に立てるので、
     * 既存の `isSuspended()` を見ている経路がそのまま遮断する。判定を増やすと
     * 新しい認証経路で片方だけ見る取りこぼしが起きる。
     *
     * これは「猶予のあいだ残っているだけの行か」を区別するためのもの。
     *
     * @return bool
     */
    public function isDeleted(): bool {
        return $this->deletedAt !== null;
    }
}
