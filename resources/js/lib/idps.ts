/**
 * 外部 IdP の表に出す名前とアイコン。
 *
 * ここに無い識別子は、そのまま名前として出す。登録だけして表示を足し忘れても、
 * 「不明」より識別子が見えているほうが手がかりになる。
 */
const IDPS: Record<string, { label: string; icon: string }> = {
    google: { label: 'Google', icon: 'google' },
    github: { label: 'GitHub', icon: 'github' },
};

/**
 * @param provider 'google' などの識別子
 * @returns 表に出す名前
 */
export function idpLabel(provider: string): string {
    return IDPS[provider]?.label ?? provider;
}

/**
 * @param provider 'google' などの識別子
 * @returns Font Awesome のアイコン名
 */
export function idpIcon(provider: string): string {
    return IDPS[provider]?.icon ?? 'right-to-bracket';
}

/**
 * ブランドのマークを持つ IdP か。
 *
 * 持たない相手に brands を指すと何も描かれないので、字形の系統を選ぶのに使う。
 *
 * @param provider 'google' などの識別子
 * @returns brands なら true
 */
export function idpIconFamily(provider: string): 'brands' | 'solid' {
    return IDPS[provider] === undefined ? 'solid' : 'brands';
}
