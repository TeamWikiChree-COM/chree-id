/**
 * 監査ログの型。
 */

/** 監査ログの1行 */
export interface AuditEventSummary {
    id: string;
    /** PHP の AuditAction と合わせる。翻訳キーは `audit.action.<action>` */
    action: string;
    /** 成立したか。false は失敗した試み */
    succeeded: boolean;
    /** 「Chrome (Windows)」のような表示名 */
    label: string;
    ipAddress: string | null;
    at: string | null;
    /** 行ごとに形の違う付随情報。読めるものだけ拾う */
    context: Record<string, unknown>;
    /** 運営が代わりにやった操作か */
    byOther: boolean;
}
