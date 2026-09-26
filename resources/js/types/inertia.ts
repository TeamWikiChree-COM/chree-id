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
                /** 追加アドレスを操作した直後だけ入る */
                accountEmail: 'added' | 'resent' | 'verified' | 'verify_failed' | 'removed' | 'promoted' | null;
                /** サービスへ渡すアドレスを変えた直後だけ true */
                serviceEmailSaved: boolean | null;
                /** サービスの連携を切った直後だけ true */
                serviceRevoked: boolean | null;
                /** アカウントを統合した直後だけ true */
                accountMerged: boolean | null;
                /** 自分のサービスを保存した直後だけ true */
                serviceSaved: boolean | null;
                /** 審査を申し込んだ直後だけ true */
                reviewRequested: boolean | null;
                /** サービスを登録した直後だけ入る。平文 secret はこの1回だけ */
                issuedSecret: { clientId: string; secret: string | null } | null;
                /** 管理画面でサービスを承認した直後だけ true */
                clientApproved: boolean | null;
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
            /** アプリ名。.env の APP_NAME */
            appName: string;
            /** ヘッダー・ログイン画面に出すロゴ。.env で未設定なら同梱のロゴ */
            appLogoUrl: string;
            /** 画面の言語。辞書そのものはバンドルに入っているので名前だけ渡す */
            locale: string;
            /** 切り替えメニューに並べる言語。config/chreeid.php の locales */
            locales: string[];
        };
    }
}

export {};
