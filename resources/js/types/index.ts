/**
 * サーバ (Inertia) から渡ってくる値の型。
 *
 * PHP 側の各コントローラが組み立てる配列と一対一で対応させる。
 * ここを直したら対応するコントローラも直すこと。
 */

/** 認証手段の種別。PHP の CredentialType と合わせる */
export type CredentialTypeValue = 'password' | 'magic_link' | 'totp' | 'passkey' | 'recovery_code' | 'oauth';

/** アカウントの出自。PHP の AccountOrigin と合わせる */
export type AccountOriginValue = 'user' | 'service';

/** DashboardController が渡すアカウント情報 */
export interface Account {
    id: string;
    email: string | null;
    displayName: string | null;
    origin: AccountOriginValue;
    emailVerified: boolean;
}

/** 一覧に並べる認証手段の1行 */
export interface CredentialSummary {
    type: CredentialTypeValue;
    label: string | null;
    lastUsedAt: string | null;
}

/**
 * HandleInertiaRequests::share が全ページに配る値。
 *
 * Inertia v3 の module augmentation なので、usePage() が総称型なしでこの型になる。
 */
declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            flash: {
                recoveryCodes: string[] | null;
            };
        };
    }
}
