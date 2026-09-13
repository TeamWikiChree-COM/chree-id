import Typography from '@mui/material/Typography';
import AppLayout from '../../../Components/AppLayout';
import AuditEventList from '../../../Components/AuditEventList';
import SectionTitle from '../../../Components/SectionTitle';
import { t } from '../../../lib/i18n';
import type { AuditEventSummary } from '../../../types';

interface IndexProps {
    events: AuditEventSummary[];
    /** 記録を残す日数 */
    keepDays: number;
}

/**
 * 運営が見る記録。全アカウント分が並ぶ。
 */
export default function Index({ events, keepDays }: IndexProps) {
    return (
        <AppLayout
            title={t('admin.audit.title')}
            lead={t('admin.audit.lead')}
            crumbs={[
                { label: t('admin.index.title'), href: '/admin' },
                { label: t('admin.audit.crumb') },
            ]}
        >
            <SectionTitle note={t('admin.audit.count', { count: events.length })}>
                {t('admin.audit.heading')}
            </SectionTitle>
            <AuditEventList events={events} emptyText={t('admin.audit.empty')} />

            <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>
                {t('admin.audit.keep_days', { days: keepDays })}
            </Typography>
        </AppLayout>
    );
}
