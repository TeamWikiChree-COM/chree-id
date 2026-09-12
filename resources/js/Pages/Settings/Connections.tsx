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
import { formatDateTime } from '../../lib/datetime';
import { idpIcon, idpIconFamily, idpLabel } from '../../lib/idps';
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

    const connected = new Set(connections.map((c) => c.provider));
    const available = providers.filter((name) => !connected.has(name));

    return (
        <AppLayout
            title="設定"
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: '設定', href: '/settings' }, { label: '外部アカウント' }]}
        >
            <SettingsTabs current="/settings/connections" />

            <Stack spacing={1.5}>
                {flash.connectionAdded && <Alert severity="success">外部アカウントを連携しました</Alert>}
                {flash.connectionRemoved && <Alert severity="success">連携を解除しました</Alert>}
                {errors.provider && <Alert severity="error">{errors.provider}</Alert>}
            </Stack>

            <SectionTitle note={`${connections.length}件`}>連携中のアカウント</SectionTitle>
            <Paper variant="outlined">
                {connections.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                        連携しているアカウントはありません
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
                                    {connection.email ?? '連携済み'}
                                    {formatDateTime(connection.connectedAt) !== null &&
                                        ` ・ ${String(formatDateTime(connection.connectedAt))} に連携`}
                                </Typography>
                            </Box>
                            <RowAction
                                destructive
                                onClick={() =>
                                    router.post('/settings/connections/remove', { id: connection.id })
                                }
                            >
                                解除
                            </RowAction>
                        </Box>
                    ))}
                </Stack>
            </Paper>

            <SectionTitle>連携できるアカウント</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                {available.length === 0 ? (
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        追加で連携できるサービスはありません
                    </Typography>
                ) : (
                    <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                            連携すると、そのアカウントでも ChreeID にログインできるようになります
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
                                    {idpLabel(provider)} と連携する
                                </Button>
                            ))}
                        </Stack>
                    </Stack>
                )}
            </Paper>
        </AppLayout>
    );
}
