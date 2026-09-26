/**
 * 認証手段と外部アカウント連携の型。
 */

/** 認証手段の種別。PHP の CredentialType と合わせる */
export type CredentialTypeValue = 'password' | 'magic_link' | 'totp' | 'passkey' | 'recovery_code' | 'oauth';

/** 一覧に並べる認証手段の1行 */
export interface CredentialSummary {
    /** 認証手段のID。削除はこれを指す (種別ではない) */
    id: string;
    type: CredentialTypeValue;
    /** パスキーの端末名など。無ければ null */
    label: string | null;
    /** 外部アカウントの連携先 ('google' など)。それ以外は null */
    provider: string | null;
    /** 連携先のメールアドレスなど、どれか見分けるための手がかり */
    detail: string | null;
    lastUsedAt: string | null;
    createdAt: string | null;
}

/** 設定画面に並べる、連携済みの外部アカウント */
export interface ExternalConnection {
    /** 認証手段のID。解除はこれを指す */
    id: string;
    /** 'google' など */
    provider: string;
    /** IdP 側のメールアドレス。分からなければ null */
    email: string | null;
    connectedAt: string | null;
    lastUsedAt: string | null;
}
