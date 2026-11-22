/** 退会の画面に並べる連携サービス */
export interface WithdrawalService {
    /** サービスアカウントのID */
    id: string;
    clientId: string;
    name: string;
    iconUrl: string | null;
    /** サービスへ削除を依頼できるか。できなければ解放するしかない */
    deletable: boolean;
}

/** 削除できるかの問い合わせに、サービスが返した答え */
export interface DeletionCheck {
    accepted: boolean;
    /** 断った理由。サービスが書いた文言 */
    reason: string | null;
    /** 物理削除までの日数 */
    graceDays: number | null;
}

/** サービス1件をどうするか。action が空なら未選択 */
export interface WithdrawalChoice {
    action: '' | 'delete' | 'release';
    /** 解放するときに持っていく認証方法のID */
    credentials: string[];
}
