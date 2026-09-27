import { t } from './i18n';

/**
 * サーバが返す誤りのコード (ServiceProviders::check()、SamlAdminController) を文言にする。
 *
 * プラグインにはサーバ側の辞書が無いので、サーバはコードだけを返し、ここで文言にする。
 */
const MESSAGES: Record<string, string> = {
    acs_url_invalid: t('admin.error.acs_url_invalid'),
    certificate_invalid: t('admin.error.certificate_invalid'),
    client_taken: t('admin.error.client_taken'),
    client_unknown: t('admin.error.client_unknown'),
    entity_id_taken: t('admin.error.entity_id_taken'),
    key_generate_failed: t('admin.error.key_generate_failed'),
    key_pair_invalid: t('admin.error.key_pair_invalid'),
    required: t('admin.error.required'),
    scopes_not_allowed: t('admin.error.scopes_not_allowed'),
};

/**
 * @param code サーバが返したコード。無ければ undefined
 * @returns 画面に出す文言。知らないコードはそのまま出す (出さないより手がかりになる)
 */
export function errorText(code: string | undefined): string | undefined {
    if (code === undefined) return undefined;

    return MESSAGES[code] ?? code;
}
