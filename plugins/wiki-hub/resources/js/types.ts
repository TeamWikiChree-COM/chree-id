/**
 * サーバ (Inertia) から渡ってくる値の型。WikiController と ListWikis が組み立てる配列と合わせる。
 *
 * 画面や部品の props はここに置かず、使うファイルに書く。
 */

export interface Wiki {
    name: string;
    url: string;
    /** ウィキのロゴ。無ければ null (頭文字で代わりを出す) */
    iconUrl: string | null;
    /** サービス側の設定画面。無ければ null */
    settingsUrl: string | null;
    /** サービスが数えていなければ null */
    views: number | null;
    updatedAt: string | null;
}

export interface WikiGroup {
    key: string;
    label: string;
    /** このサービスから取れなかった */
    failed: boolean;
    wikis: Wiki[];
}
