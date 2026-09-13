import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '../../Components/AppLayout';
import RowAction from '../../Components/RowAction';
import SectionTitle from '../../Components/SectionTitle';
import SettingsTabs from '../../Components/SettingsTabs';
import DeviceRow from '../../Components/Settings/DeviceRow';
import { useConfirm } from '../../lib/confirm';
import { formatDateTime, formatRelative } from '../../lib/datetime';
import { t } from '../../lib/i18n';
import type { LoginSessionSummary, TrustedDeviceSummary } from '../../types';

interface DevicesProps {
    sessions: LoginSessionSummary[];
    trustedDevices: TrustedDeviceSummary[];
}

/**
 * 端末の管理。
 *
 * 「ログイン中」と「信頼済み」は別物。前者は切ればその場でログアウトになり、
 * 後者は切っても入り直せる (次から2段階目を聞かれるだけ)。混ぜて見せない。
 */
export default function Devices({ sessions, trustedDevices }: DevicesProps) {
    const { flash } = usePage().props;
    const { ask, dialog } = useConfirm();

    const endSession = (session: LoginSessionSummary): void => {
        ask({
            title: session.isCurrent ? t('settings.devices.sessions.logout_title') : t('settings.devices.sessions.end_title'),
            description: session.isCurrent
                ? t('settings.devices.sessions.logout_description')
                : t('settings.devices.sessions.end_description', { label: session.label }),
            confirmText: session.isCurrent ? t('settings.devices.sessions.logout_confirm') : t('settings.devices.sessions.end_confirm'),
            onConfirm: () => router.post('/settings/devices/sessions/revoke', { id: session.id }),
        });
    };

    return (
        <AppLayout
            title={t('settings.title')}
            crumbs={[{ label: t('settings.title'), href: '/settings' }, { label: t('settings.devices.crumb') }]}
        >
            <SettingsTabs current="/settings/devices" />

            <Stack spacing={1.5}>
                {flash.sessionsRevoked !== null && flash.sessionsRevoked > 0 && (
                    <Alert severity="success">{t('settings.devices.sessions_revoked', { count: flash.sessionsRevoked })}</Alert>
                )}
                {flash.trustRevoked && <Alert severity="success">{t('settings.devices.trust_revoked')}</Alert>}
            </Stack>

            <SectionTitle note={t('settings.devices.sessions.count', { count: sessions.length })}>{t('settings.devices.sessions.heading')}</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {sessions.map((session) => (
                        <DeviceRow
                            key={session.id}
                            label={session.label}
                            detail={[session.ipAddress, formatRelative(session.lastActiveAt) && t('settings.devices.sessions.last_active', { time: String(formatRelative(session.lastActiveAt)) })]}
                            badge={session.isCurrent ? <Chip size="small" label={t('settings.devices.current_badge')} /> : null}
                            action={
                                <RowAction
                                    destructive
                                    onClick={() => endSession(session)}
                                >
                                    {session.isCurrent ? t('settings.devices.sessions.logout') : t('settings.devices.sessions.end')}
                                </RowAction>
                            }
                        />
                    ))}
                </Stack>
            </Paper>

            {sessions.length > 1 && (
                <Box sx={{ mt: 1.5 }}>
                    <Button
                        variant="outlined"
                        color="error"
                        onClick={() =>
                            ask({
                                title: t('settings.devices.sessions.revoke_others_title'),
                                description: t('settings.devices.sessions.revoke_others_description', { count: sessions.length - 1 }),
                                confirmText: t('settings.devices.sessions.revoke_others_confirm'),
                                expected: t('settings.devices.sessions.revoke_others_expected'),
                                onConfirm: () => router.post('/settings/devices/sessions/revoke-others'),
                            })
                        }
                    >
                        {t('settings.devices.sessions.revoke_others')}
                    </Button>
                </Box>
            )}

            <SectionTitle note={t('settings.devices.trusted.count', { count: trustedDevices.length })}>{t('settings.devices.trusted.heading')}</SectionTitle>
            <Paper variant="outlined">
                {trustedDevices.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('settings.devices.trusted.empty')}
                    </Typography>
                )}

                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {trustedDevices.map((device) => (
                        <DeviceRow
                            key={device.id}
                            label={device.label}
                            detail={[device.ipAddress, t('settings.devices.trusted.expires', { date: String(formatDateTime(device.expiresAt)) })]}
                            badge={device.isCurrent ? <Chip size="small" label={t('settings.devices.current_badge')} /> : null}
                            action={
                                <RowAction
                                    destructive
                                    onClick={() =>
                                        ask({
                                            title: t('settings.devices.trusted.revoke_title'),
                                            description: t('settings.devices.trusted.revoke_description', { label: device.label }),
                                            confirmText: t('settings.devices.trusted.revoke'),
                                            onConfirm: () =>
                                                router.post('/settings/devices/trusted/revoke', { id: device.id }),
                                        })
                                    }
                                >
                                    {t('settings.devices.trusted.revoke')}
                                </RowAction>
                            }
                        />
                    ))}
                </Stack>
            </Paper>

            {trustedDevices.length > 0 && (
                <Box sx={{ mt: 1.5 }}>
                    <Button
                        variant="outlined"
                        color="error"
                        onClick={() =>
                            ask({
                                title: t('settings.devices.trusted.revoke_all_title'),
                                description: t('settings.devices.trusted.revoke_all_description'),
                                confirmText: t('settings.devices.trusted.revoke_all'),
                                onConfirm: () => router.post('/settings/devices/trusted/revoke-all'),
                            })
                        }
                    >
                        {t('settings.devices.trusted.revoke_all')}
                    </Button>
                </Box>
            )}

            {dialog}
        </AppLayout>
    );
}
