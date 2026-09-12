import { t } from './i18n';
import type { TrustValue } from '../types';

/** 信頼状態の表示。値そのものを出すと何が起きるか分からないので言い換える */
const TRUST_LABELS: Record<TrustValue, string> = {
    official: t('admin.clients.trust.official'),
    approved: t('admin.clients.trust.approved'),
    unapproved: t('admin.clients.trust.unapproved'),
    disabled: t('admin.clients.trust.disabled'),
};

/**
 * @param trust サービスの信頼状態
 * @returns 表に出す言い方
 */
export function trustLabel(trust: TrustValue): string {
    return TRUST_LABELS[trust];
}
