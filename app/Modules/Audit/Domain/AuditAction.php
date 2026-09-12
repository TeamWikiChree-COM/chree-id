<?php
namespace App\Modules\Audit\Domain;

// 監査ログに残す出来事の種類

// **本人の身に何が起きたか**が分かる粒度で切る。画面の操作ごとではない。
// 値は `audit.action.<value>` の翻訳キーにそのまま使う。
enum AuditAction: string {
    case LOGIN_SUCCEEDED = 'login.succeeded';
    case LOGIN_FAILED = 'login.failed';

    case CREDENTIAL_ADDED = 'credential.added';
    case CREDENTIAL_REMOVED = 'credential.removed';
    case CREDENTIAL_RENAMED = 'credential.renamed';
    case PASSWORD_CHANGED = 'password.changed';
    case RECOVERY_CODES_GENERATED = 'recovery_codes.generated';

    case EMAIL_CHANGE_REQUESTED = 'email.change_requested';
    case EMAIL_CHANGE_CANCELLED = 'email.change_cancelled';
    case EMAIL_CHANGED = 'email.changed';
    case EMAIL_VERIFIED = 'email.verified';
    case PROFILE_UPDATED = 'profile.updated';
    case ICON_CHANGED = 'icon.changed';

    case DEVICE_TRUSTED = 'device.trusted';
    case DEVICE_TRUST_REVOKED = 'device.trust_revoked';
    case SESSION_REVOKED = 'session.revoked';

    case CONNECTION_ADDED = 'connection.added';
    case CONNECTION_REMOVED = 'connection.removed';
    case SERVICE_REVOKED = 'service.revoked';
    case SERVICE_SPLIT = 'service.split';
    case SERVICE_REGISTERED = 'service.registered';
    case SERVICE_REVIEW_REQUESTED = 'service.review_requested';

    case ACCOUNT_MERGED = 'account.merged';
    case ACCOUNT_WITHDRAWN = 'account.withdrawn';

    // 運営としての操作。actor は対象と別人になる
    case ADMIN_ACCOUNT_CREATED = 'admin.account.created';
    case ADMIN_ACCOUNT_UPDATED = 'admin.account.updated';
    case ADMIN_ACCOUNT_ACTED = 'admin.account.acted';

    /**
     * ログインの記録か。
     *
     * 本人向けの「最近のログイン」はこれで絞る。
     *
     * @return bool
     */
    public function isLogin(): bool {
        return $this === self::LOGIN_SUCCEEDED || $this === self::LOGIN_FAILED;
    }
}
