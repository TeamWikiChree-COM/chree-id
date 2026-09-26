/**
 * アカウントと、その持ち物 (アイコン・追加アドレス) の型。
 */

/** アカウントの種別。PHP の AccountOrigin と合わせる */
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

/** 主アドレスとは別に持つ追加のアドレス */
export interface AccountEmail {
    id: string;
    email: string;
    verified: boolean;
    /** 確認リンクの期限。確認待ちでなければ null */
    pendingUntil: string | null;
}

/** サービスへ渡すアドレスとして選べるもの */
export interface EmailOption {
    email: string;
    /** 「+」付き版を作れるドメインか */
    plus: boolean;
}
