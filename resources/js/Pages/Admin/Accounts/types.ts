export interface AdminAccount {
    id: string;
    email: string | null;
    displayName: string | null;
    origin: 'user' | 'service';
    isEmailVerified: boolean;
    isSuspended: boolean;
    /** 退会済み。猶予のあいだ行だけ残っている */
    isDeleted: boolean;
    /** 退会日時。退会していなければ null */
    deletedAt: string | null;
    isAdmin: boolean;
    createdAt: string;
    credentialTypes: string[];
    /**
     * 紐付いているサービス上の人格。
     *
     * `origin` は出自の記録なので、**いま何に紐付いているかはこちらを見る**。
     * 1人が同じサービスに複数持てるので配列。
     */
    services: AdminAccountService[];
}

/** 管理画面に出す、サービス上の人格1つ */
export interface AdminAccountService {
    clientId: string;
    /** 接続サービスの表示名。クライアントが消えている場合は clientId が入る */
    name: string;
    /** サービス側での利用者の識別子。OIDC で入っただけなら null */
    serviceUserId: string | null;
}
