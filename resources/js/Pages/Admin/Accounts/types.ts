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

/** 一覧のページ送り */
export interface AdminPagination {
    page: number;
    lastPage: number;
    total: number;
}

/** 一覧の絞り込み。空文字は「絞らない」 */
export interface AdminAccountFilters {
    q: string;
    kind: '' | 'user' | 'service';
    status: '' | 'active' | 'suspended' | 'deleted';
    client: string;
}

/** 絞り込みに出す接続サービス */
export interface AdminClientOption {
    id: string;
    name: string;
}

/** 詳細に出すサービスアカウント。一覧の AdminAccountService より細かい */
export interface AdminServiceLink {
    id: string;
    clientId: string;
    name: string;
    serviceUserId: string | null;
    /** サービスに渡している識別子。まだ採番していなければ null */
    sub: string | null;
    claimedAt: string | null;
    createdAt: string | null;
}

/** DetectAccountIssues の問題の種類 */
export type AdminAccountIssue = 'origin_behind' | 'missing_user_account' | 'multi_service';
