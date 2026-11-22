import { t } from './i18n';
import { idpLabel } from './idps';

/** 分離 (解放) で持っていける認証方法 */
export interface SplitOption {
    id: string;
    type: string;
    /** 同じ種別が並んだときの見分け (連携先のメールアドレスなど) */
    detail: string | null;
    /** 外部ログインの連携先 (google など)。それ以外は null */
    provider: string | null;
}

/** 画面に出す名前。移せないものはサーバ側で候補から外れている */
export const SPLIT_CREDENTIAL_LABELS: Record<string, string> = {
    password: t('settings.split.credential.password'),
    totp: t('settings.split.credential.totp'),
    magic_link: t('settings.split.credential.magic_link'),
    oauth: t('settings.split.credential.oauth'),
};

/**
 * @param option 候補
 * @returns 外部ログインなら連携先を入れた名前。それ以外は undefined (種別の表示名を使う)
 */
export function splitCredentialName(option: SplitOption): string | undefined {
    if (option.provider === null) return undefined;

    return t('settings.split.credential.oauth_named', { name: idpLabel(option.provider) });
}

/**
 * @param option 候補
 * @returns 画面に出す名前。見分けの手がかりがあれば添える
 */
export function splitCredentialLabel(option: SplitOption): string {
    const name = splitCredentialName(option) ?? SPLIT_CREDENTIAL_LABELS[option.type] ?? option.type;

    return option.detail ? `${name} (${option.detail})` : name;
}
