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
}
