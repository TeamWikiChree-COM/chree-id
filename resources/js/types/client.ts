/**
 * 接続サービス (OAuth クライアント) の型。
 */

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
    /** 利用者を案内する設定画面。未設定なら null */
    settingsUrl: string | null;
    /** 第三者が登録したものか */
    hasOwner: boolean;
    /** 審査を申し込んだ日時。未申請なら null */
    reviewRequestedAt: string | null;
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

/** サービス登録フォームの URL 群。利用者用と管理用のフォームで共有する */
export interface ServiceUrls {
    redirect_uris: string[];
    icon_url: string;
    settings_url: string;
}

/** 第三者が自分で登録したサービス */
export interface OwnedService {
    /** client_id */
    id: string;
    name: string;
    trust: TrustValue;
    iconUrl: string | null;
    /** 利用者を案内する設定画面。未設定なら null */
    settingsUrl: string | null;
    redirectUris: string[];
    scopes: string;
    /** 審査を申し込んだ日時。未申請なら null */
    reviewRequestedAt: string | null;
}

/** 利用者から見た、連携しているサービス */
export interface ConnectedService {
    /** サービスアカウントのID。分離はこれを指す */
    id: string;
    clientId: string;
    /** アイコンの URL。未設定なら null */
    iconUrl: string | null;
    /** サービス側の設定画面。指定が無ければ導線を出さない */
    settingsUrl: string | null;
    /** サービス側での識別子。OIDC 経由でできたものは分からない */
    serviceUserId: string | null;
    /** このサービスへ渡すと決めたアドレス。null なら主アドレスを渡している */
    email: string | null;
    name: string;
    trust: TrustValue;
    connectedAt: string | null;
}
