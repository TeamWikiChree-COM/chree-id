import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '../../Components/AppLayout';
import Icon from '../../Components/Icon';
import RowAction from '../../Components/RowAction';
import SectionTitle from '../../Components/SectionTitle';
import SettingsTabs from '../../Components/SettingsTabs';
import { useConfirm } from '../../lib/confirm';
import { formatDateTime } from '../../lib/datetime';
import { idpIcon, idpIconFamily, idpLabel } from '../../lib/idps';
import { t } from '../../lib/i18n';
import type { ExternalConnection } from '../../types';

interface ConnectionsProps {
    connections: ExternalConnection[];
    /** 設定が揃っていて連携できる IdP */
    providers: string[];
}

/**
 * 外部アカウントの連携。
 *
 * ログインのついでではなく、後から足したり外したりする画面。
 * 連携を始めるのは POST なので、リンクではなくボタンで出す。
 */
export default function Connections({ connections, providers }: ConnectionsProps) {
    const { flash, errors } = usePage().props;

    const { ask, dialog } = useConfirm();

    const connected = new Set(connections.map((c) => c.provider));
    const available = providers.filter((name) => !connected.has(name));

    return (
        <AppLayout
            title={t('settings.title')}
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: t('settings.title'), href: '/settings' }, { label: t('settings.connections.crumb') }]}
        >
            <SettingsTabs current="/settings/connections" />

            <Stack spacing={1.5}>
                {flash.connectionAdded && <Alert severity="success">{t('settings.connections.added')}</Alert>}
                {flash.connectionRemoved && <Alert severity="success">{t('settings.connections.removed')}</Alert>}
                {errors.provider && <Alert severity="error">{errors.provider}</Alert>}
            </Stack>

            <SectionTitle note={t('settings.connections.count', { count: connections.length })}>{t('settings.connections.heading')}</SectionTitle>
            <Paper variant="outlined">
                {connections.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('settings.connections.empty')}
                    </Typography>
                )}

                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {connections.map((connection) => (
                        <Box
                            key={connection.id}
                            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}
                        >
                            <Box>
                                <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                    <Icon
                                        name={idpIcon(connection.provider)}
                                        family={idpIconFamily(connection.provider)}
                                        sx={{ width: 18, textAlign: 'center', color: 'text.disabled' }}
                                    />
                                    {idpLabel(connection.provider)}
                                </Typography>
                                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                    {connection.email ?? t('settings.connections.connected_label')}
                                    {formatDateTime(connection.connectedAt) !== null &&
                                        t('settings.connections.connected_at', { date: String(formatDateTime(connection.connectedAt)) })}
                                </Typography>
                            </Box>
                            <RowAction
                                destructive
                                onClick={() =>
                                    ask({
                                        title: t('settings.connections.remove.title', { provider: idpLabel(connection.provider) }),
                                        description: t('settings.connections.remove.description', { provider: idpLabel(connection.provider) }),
                                        confirmText: t('settings.connections.remove.confirm'),
                                        onConfirm: () =>
                                            router.post('/settings/connections/remove', { id: connection.id }),
                                    })
                                }
                            >
                                {t('settings.connections.remove.action')}
                            </RowAction>
                        </Box>
                    ))}
                </Stack>
            </Paper>

            <SectionTitle>{t('settings.connections.available.heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                {available.length === 0 ? (
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('settings.connections.available.empty')}
                    </Typography>
                ) : (
                    <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                            {t('settings.connections.available.description')}
                        </Typography>

                        <Stack direction="row" spacing={1.5} sx={{ flexWrap: 'wrap' }}>
                            {available.map((provider) => (
                                <Button
                                    key={provider}
                                    variant="outlined"
                                    color="inherit"
                                    startIcon={<Icon name={idpIcon(provider)} family={idpIconFamily(provider)} />}
                                    onClick={() => router.post(`/settings/connections/${provider}`)}
                                >
                                    {t('settings.connections.available.connect', { provider: idpLabel(provider) })}
                                </Button>
                            ))}
                        </Stack>
                    </Stack>
                )}
            </Paper>

            {dialog}
        </AppLayout>
    );
}
