import { idpIcon, idpIconFamily, idpLabel } from './idps';
import { t } from './i18n';
import type { CredentialSummary, CredentialTypeValue } from '../types';

/**
 * 種別のラベル。
 *
 * `t()` の結果を呼ぶたびに引く。モジュール読み込み時の定数にすると、
 * ロケール確定 (`initTranslations`) より前に評価されてしまう。
 *
 * @param type 認証手段の種別
 */
function typeLabel(type: CredentialTypeValue): string | undefined {
    const labels: Partial<Record<CredentialTypeValue, string>> = {
        password: t('credential.type.password'),
        magic_link: t('credential.type.magic_link'),
        totp: t('credential.type.totp'),
        passkey: t('credential.type.passkey'),
        oauth: t('credential.type.oauth'),
    };

    return labels[type];
}

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

    return typeLabel(credential.type) ?? credential.type;
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

/**
 * 認証手段を持たないログイン経路のラベル。PHP の LoginMethod にだけある値。
 *
 * `typeLabel` と同じ理由で、呼ぶたびに引く。
 *
 * @param type LoginMethod の value
 */
function methodOnlyLabel(type: string): string | undefined {
    const labels: Record<string, string> = {
        registration: t('credential.method.registration'),
        claim: t('credential.method.claim'),
    };

    return labels[type];
}

/**
 * ログイン方式の名前。
 *
 * 履歴に出す用。`CredentialSummary` を持たない場面 (記録された文字列だけ) で使う。
 *
 * @param method PHP の LoginMethod の value、または 'oauth:google' 形式
 */
export function methodLabel(method: string): string {
    const [type = method, provider] = method.split(':');
    if (provider !== undefined && provider !== '') return idpLabel(provider);

    return typeLabel(type as CredentialTypeValue) ?? methodOnlyLabel(type) ?? method;
}
