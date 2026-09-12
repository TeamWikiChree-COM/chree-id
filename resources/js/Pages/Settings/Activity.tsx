import Typography from '@mui/material/Typography';
import AppLayout from '../../Components/AppLayout';
import AuditEventList from '../../Components/AuditEventList';
import SectionTitle from '../../Components/SectionTitle';
import SettingsTabs from '../../Components/SettingsTabs';
import { t } from '../../lib/i18n';
import type { AuditEventSummary } from '../../types';

interface ActivityProps {
    events: AuditEventSummary[];
    /** 記録を残す日数 */
    keepDays: number;
}

/**
 * 本人が見る記録。
 *
 * ログインだけでなく、認証手段やメールアドレスの変更も並ぶ。
 * **乗っ取られたときに本人が気付ける唯一の場所**なので、操作の導線は置かない。
 */
export default function Activity({ events, keepDays }: ActivityProps) {
    return (
        <AppLayout
            title={t('settings.title')}
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: t('settings.title'), href: '/settings' },
                { label: t('settings.activity.crumb') },
            ]}
        >
            <SettingsTabs current="/settings/activity" />

            <SectionTitle note={t('settings.activity.count', { count: events.length })}>
                {t('settings.activity.heading')}
            </SectionTitle>
            <AuditEventList events={events} emptyText={t('settings.activity.empty')} />

            <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>
                {t('settings.activity.footer_hint', { days: keepDays })}
            </Typography>
        </AppLayout>
    );
}
