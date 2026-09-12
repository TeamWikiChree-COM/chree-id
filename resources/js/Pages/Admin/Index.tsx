import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import AppLayout from '../../Components/AppLayout';
import NavRow from '../../Components/NavRow';
import SectionTitle from '../../Components/SectionTitle';
import StatCard from '../../Components/StatCard';
import { t } from '../../lib/i18n';

interface AdminIndexProps {
    stats: {
        clients: number;
        accounts: number;
        suspended: number;
    };
    /** まだ適用されていないマイグレーションの数 */
    pendingMigrations: number;
}

export default function AdminIndex({ stats, pendingMigrations }: AdminIndexProps) {
    return (
        <AppLayout
            title={t('admin.index.title')}
            lead={t('admin.index.lead')}
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: t('admin.crumb') }]}
        >
            <Stack direction="row" spacing={1.5}>
                <StatCard label={t('admin.index.stats.clients')} value={stats.clients} />
                <StatCard label={t('admin.index.stats.accounts')} value={stats.accounts} />
                <StatCard label={t('admin.index.stats.suspended')} value={stats.suspended} />
            </Stack>

            <SectionTitle>{t('admin.index.manage')}</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    <NavRow
                        icon="users"
                        title={t('admin.index.nav.accounts.title')}
                        description={t('admin.index.nav.accounts.description')}
                        href="/admin/accounts"
                    />
                    <NavRow
                        icon="plug"
                        title={t('admin.index.nav.clients.title')}
                        description={t('admin.index.nav.clients.description')}
                        href="/admin/clients"
                    />
                    <NavRow
                        icon="database"
                        title={t('admin.index.nav.migrations.title')}
                        description={
                            pendingMigrations === 0
                                ? t('admin.index.nav.migrations.up_to_date')
                                : t('admin.index.nav.migrations.pending', { count: pendingMigrations })
                        }
                        href="/admin/migrations"
                    />
                    <NavRow
                        icon="clipboard-list"
                        title={t('admin.index.nav.audit.title')}
                        description={t('admin.index.nav.audit.description')}
                        href="/admin/audit"
                    />
                    <NavRow
                        icon="file-lines"
                        title={t('admin.index.nav.logs.title')}
                        description={t('admin.index.nav.logs.description')}
                        href="/admin/logs"
                    />
                    <NavRow
                        icon="broom"
                        title={t('admin.index.nav.maintenance.title')}
                        description={t('admin.index.nav.maintenance.description')}
                        href="/admin/maintenance"
                    />
                    <NavRow
                        icon="cloud-arrow-up"
                        title={t('admin.index.nav.backups.title')}
                        description={t('admin.index.nav.backups.description')}
                        href="/admin/backups"
                    />
                </Stack>
            </Paper>
        </AppLayout>
    );
}
