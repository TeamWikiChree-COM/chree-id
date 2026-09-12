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

/** アイコンの出どころ。PHP の IconSource と合わせる */
export type IconSourceValue = 'none' | 'gravatar' | 'upload';

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

/** アプリケーションログの1件。PHP の LogFile が組み立てる */
export interface LogEntry {
    at: string;
    channel: string;
    /** ERROR / WARNING など */
    level: string;
    message: string;
    /** スタックトレース。無ければ空文字 */
    trace: string;
}

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

/** 2段階目を省略してよい端末の1行 */
export interface TrustedDeviceSummary {
    id: string;
    label: string;
    ipAddress: string | null;
    lastUsedAt: string | null;
    expiresAt: string;
    isCurrent: boolean;
}

/** サービスの信頼状態。PHP の ServiceTrust と合わせる */
export type TrustValue = 'official' | 'approved' | 'unapproved' | 'disabled';

/** 管理画面が扱う接続サービス。client_secret はここには載らない */
export interface OAuthClient {
    id: string;
    /** 運営が識別に使う名前。どの言語でも出せる最後の拠り所 */
    name: string;
    /** 言語ごとの表示名。入っていない言語は name を出す */
    names: Record<string, string>;
    redirectUris: string[];
    scopes: string;
    isConfidential: boolean;
    trust: TrustValue;
    /** アイコンの URL。未設定なら null */
    iconUrl: string | null;
    /** このサービスのサービスアカウント数 */
    serviceAccounts: number;
    /** そのうち、束ねる人格を持つに至った数 */
    migratedAccounts: number;
    /** 同意画面を省略するか。信頼状態とは別の設定 */
    skipsConsent: boolean;
    /** サービスアカウントを扱えるか。信頼状態とは別の設定 */
    canProvision: boolean;
    createdAt: string | null;
}

/** 利用者から見た、連携しているサービス */
export interface ConnectedService {
    /** サービスアカウントのID。分離はこれを指す */
    id: string;
    clientId: string;
    /** アイコンの URL。未設定なら null */
    iconUrl: string | null;
    /** サービス側での識別子。OIDC 経由でできたものは分からない */
    serviceUserId: string | null;
    name: string;
    trust: TrustValue;
    connectedAt: string | null;
    /** 有効なアクセストークンが残っているか */
    hasActiveToken: boolean;
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
                /** パスワード再設定の直後だけ true。ログイン画面で知らせる */
                passwordReset: boolean | null;
                /** プロフィール保存の直後だけ true */
                profileSaved: boolean | null;
                /** 確認メールを送った直後だけ true */
                verificationSent: boolean | null;
                /** 確認リンクを開いた直後だけ入る。false なら期限切れなど */
                emailVerified: boolean | null;
                /** 変更の確認メールを送った直後だけ true */
                emailChangeSent: boolean | null;
                /** 確認待ちの変更を取り消した直後だけ true */
                emailChangeCancelled: boolean | null;
                /** 変更リンクを開いた直後だけ入る。false なら期限切れなど */
                emailChanged: boolean | null;
                /** サービスの連携を切った直後だけ true */
                serviceRevoked: boolean | null;
                /** アカウントを統合した直後だけ true */
                accountMerged: boolean | null;
                /** マイグレーションを走らせた直後だけ入る artisan の出力 */
                migrationOutput: string | null;
                /** 掃除を流した直後だけ入る削除件数 */
                prunedTokens: number | null;
                /** ログを空にした直後だけ true */
                logCleared: boolean | null;
                /** 管理画面でアカウントを作った直後だけ true */
                accountCreated: boolean | null;
                /** パスワードを変更した直後だけ true */
                passwordChanged: boolean | null;
                /** アイコンを保存した直後だけ true */
                iconSaved: boolean | null;
                /** 外部アカウントを連携した直後だけ true */
                connectionAdded: boolean | null;
                /** 外部アカウントの連携を切った直後だけ true */
                connectionRemoved: boolean | null;
                /** 端末を切った直後だけ入る台数 */
                sessionsRevoked: number | null;
                /** 端末の信頼を取り消した直後だけ true */
                trustRevoked: boolean | null;
                /** バックアップを取った直後だけ入る結果 */
                backupTaken: {
                    name: string;
                    bytes: number;
                    tables: number;
                    rows: number;
                    /** 消した古い控えの数 */
                    pruned: number;
                } | null;
            };
            /** Turnstile 未設定なら null。その場合ウィジェットを出さない */
            turnstileSiteKey: string | null;
            /** 管理画面への導線を出すか */
            isAdmin: boolean;
            /** 設定が揃っている外部 IdP の識別子 (例: ["google"]) */
            externalIdps: string[];
            /** ヘッダーの中身を切り替えるためだけの値 */
            isLoggedIn: boolean;
            /** ログイン中の本人のアイコン。未設定なら null */
            iconUrl: string | null;
            /** 画面の言語。辞書そのものはバンドルに入っているので名前だけ渡す */
            locale: string;
            /** 切り替えメニューに並べる言語。config/chreeid.php の locales */
            locales: string[];
        };
    }
}
