import { t } from '../../../lib/i18n';

/** 認証方式の表示ラベル。無い種別は種別名のまま出す */
export const CREDENTIAL_LABELS: Record<string, string> = {
    password: t('admin.accounts.row.credential.password'),
    passkey: t('admin.accounts.row.credential.passkey'),
    totp: t('admin.accounts.row.credential.totp'),
    magic_link: t('admin.accounts.row.credential.magic_link'),
    oauth: t('admin.accounts.row.credential.oauth'),
};
