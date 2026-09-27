/**
 * 外部 IdP の表に出す名前とアイコン。
 *
 * 中身はサーバが共有 props の idpDisplays で配る。IdP 自身が持っているので、
 * プラグインで追加した IdP もここを触らずに出せる。
 *
 * 一覧に無い識別子は、そのまま名前として出す。登録を解除した IdP の連携が残っていても、
 * 「不明」より識別子が見えているほうが手がかりになる。
 */
export interface IdpDisplay {
    label: string;
    /** Font Awesome のアイコン名 */
    icon: string;
    family: 'brands' | 'solid';
}

let displays: Record<string, IdpDisplay> = {};

/**
 * サーバから届いた一覧を入れる。app.tsx が最初のページと遷移のたびに呼ぶ。
 *
 * props から読む hook にしないのは、コンポーネントの外 (credentials.ts の表など) からも引くため。
 *
 * @param next 識別子 => 表示
 */
export function setIdpDisplays(next: Record<string, IdpDisplay> | undefined): void {
    if (next !== undefined) displays = next;
}

/**
 * @param provider 'google' などの識別子
 * @returns 表に出す名前
 */
export function idpLabel(provider: string): string {
    return displays[provider]?.label ?? provider;
}

/**
 * @param provider 'google' などの識別子
 * @returns Font Awesome のアイコン名
 */
export function idpIcon(provider: string): string {
    return displays[provider]?.icon ?? 'right-to-bracket';
}

/**
 * アイコンの字形の系統。
 *
 * ブランドのマークでない相手に brands を指すと何も描かれないので、IdP ごとに持たせている。
 *
 * @param provider 'google' などの識別子
 * @returns brands か solid
 */
export function idpIconFamily(provider: string): 'brands' | 'solid' {
    return displays[provider]?.family ?? 'solid';
}
