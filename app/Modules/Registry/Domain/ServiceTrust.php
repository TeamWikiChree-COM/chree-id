<?php
namespace App\Modules\Registry\Domain;

/**
 * サービスの信頼状態 (要件定義書 4.2)
 */
enum ServiceTrust: string {
    /** Chree が公式に提供・運営するサービス */
    case OFFICIAL = 'official';

    /** Chree による確認・承認を受けたサービス */
    case APPROVED = 'approved';

    /** 登録されているが未承認 */
    case UNAPPROVED = 'unapproved';

    /** 利用を停止したサービス */
    case DISABLED = 'disabled';

    /**
     * 同意画面を省略してよいか。
     *
     * 公式サービスは ChreeID 自身の一部とみなせるので、毎回の同意を求めない。
     *
     * @return bool
     */
    public function skipsConsent(): bool {
        return $this === self::OFFICIAL;
    }

    /**
     * 認証に使えるか。
     *
     * @return bool
     */
    public function isUsable(): bool {
        return $this !== self::DISABLED;
    }
}
