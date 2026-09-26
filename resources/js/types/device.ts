/**
 * ログイン中の端末と、2段階目を省略してよい端末の型。
 */

/** ログイン中の端末の1行 */
export interface LoginSessionSummary {
    id: string;
    /** 「Chrome (Windows)」のような表示名 */
    label: string;
    ipAddress: string | null;
    lastActiveAt: string | null;
    /** いま見ている端末か */
    isCurrent: boolean;
}

/** 2段階目を省略してよい端末の1行 */
export interface TrustedDeviceSummary {
    id: string;
    label: string;
    ipAddress: string | null;
    lastUsedAt: string | null;
    expiresAt: string;
    isCurrent: boolean;
}
