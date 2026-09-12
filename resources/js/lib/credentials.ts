import { idpIcon, idpIconFamily, idpLabel } from './idps';
import type { CredentialSummary, CredentialTypeValue } from '../types';

const TYPE_LABELS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'パスワード',
    magic_link: 'メールのリンク',
    totp: '認証アプリ (TOTP)',
    passkey: 'パスキー',
    oauth: '外部アカウント',
};

const TYPE_ICONS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'key',
    magic_link: 'envelope',
    totp: 'mobile-screen',
    passkey: 'fingerprint',
    oauth: 'right-to-bracket',
};

/**
 * 一覧に出す名前。
 *
 * 外部アカウントとパスキーは複数持てるので、種別名だけだと同じ行が並ぶ。
 * 連携先や端末名が分かるならそちらを名前にする。
 *
 * @param credential 認証手段の1行
 */
export function credentialLabel(credential: CredentialSummary): string {
    if (credential.provider !== null) return idpLabel(credential.provider);
    if (credential.label !== null) return credential.label;

    return TYPE_LABELS[credential.type] ?? credential.type;
}

/**
 * @param credential 認証手段の1行
 * @returns Font Awesome のアイコン名
 */
export function credentialIcon(credential: CredentialSummary): string {
    if (credential.provider !== null) return idpIcon(credential.provider);

    return TYPE_ICONS[credential.type] ?? 'circle-question';
}

/**
 * @param credential 認証手段の1行
 * @returns 字形の系統。ブランドのマークを持つ相手だけ brands
 */
export function credentialIconFamily(credential: CredentialSummary): 'brands' | 'solid' {
    return credential.provider === null ? 'solid' : idpIconFamily(credential.provider);
}

/** 認証手段を持たないログイン経路。PHP の LoginMethod にだけある値 */
const METHOD_LABELS: Record<string, string> = {
    registration: '新規登録',
    claim: 'アカウントの引き取り',
};

/**
 * ログイン方式の名前。
 *
 * 履歴に出す用。`CredentialSummary` を持たない場面 (記録された文字列だけ) で使う。
 *
 * @param method PHP の LoginMethod の value、または 'oauth:google' 形式
 */
export function methodLabel(method: string): string {
    const [type, provider] = method.split(':');
    if (provider !== undefined && provider !== '') return idpLabel(provider);

    return TYPE_LABELS[type as CredentialTypeValue] ?? METHOD_LABELS[type] ?? method;
}
