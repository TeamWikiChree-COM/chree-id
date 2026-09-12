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
            title: session.isCurrent ? 'ログアウトしますか' : 'この端末のログインを終了しますか',
            description: session.isCurrent
                ? 'この端末からログアウトします'
                : `${session.label} は次から入り直しが必要になります`,
            confirmText: session.isCurrent ? 'ログアウトする' : '終了する',
            onConfirm: () => router.post('/settings/devices/sessions/revoke', { id: session.id }),
        });
    };

    return (
        <AppLayout
            title="設定"
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: '設定', href: '/settings' }, { label: '端末' }]}
        >
            <SettingsTabs current="/settings/devices" />

            <Stack spacing={1.5}>
                {flash.sessionsRevoked !== null && flash.sessionsRevoked > 0 && (
                    <Alert severity="success">{flash.sessionsRevoked} 台のログインを終了しました</Alert>
                )}
                {flash.trustRevoked && <Alert severity="success">端末の信頼を取り消しました</Alert>}
            </Stack>

            <SectionTitle note={`${sessions.length}台`}>ログイン中の端末</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {sessions.map((session) => (
                        <DeviceRow
                            key={session.id}
                            label={session.label}
                            detail={[session.ipAddress, formatRelative(session.lastActiveAt) && `最終利用 ${String(formatRelative(session.lastActiveAt))}`]}
                            badge={session.isCurrent ? <Chip size="small" label="この端末" /> : null}
                            action={
                                <RowAction
                                    destructive
                                    onClick={() => endSession(session)}
                                >
                                    {session.isCurrent ? 'ログアウト' : '終了'}
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
                                title: 'この端末以外をすべてログアウトしますか',
                                description: `${String(sessions.length - 1)} 台のログインを終了します。心当たりのない端末があるときは、あわせてパスワードも変えてください`,
                                confirmText: 'すべて終了する',
                                expected: 'ログアウト',
                                onConfirm: () => router.post('/settings/devices/sessions/revoke-others'),
                            })
                        }
                    >
                        この端末以外をすべてログアウト
                    </Button>
                </Box>
            )}

            <SectionTitle note={`${trustedDevices.length}台`}>2段階認証を省略する端末</SectionTitle>
            <Paper variant="outlined">
                {trustedDevices.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                        登録された端末はありません。2段階目の入力画面で「この端末を記憶する」を選ぶと追加されます
                    </Typography>
                )}

                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {trustedDevices.map((device) => (
                        <DeviceRow
                            key={device.id}
                            label={device.label}
                            detail={[device.ipAddress, `${String(formatDateTime(device.expiresAt))} まで`]}
                            badge={device.isCurrent ? <Chip size="small" label="この端末" /> : null}
                            action={
                                <RowAction
                                    destructive
                                    onClick={() =>
                                        ask({
                                            title: '端末の信頼を取り消しますか',
                                            description: `${device.label} では次のログインから2段階目を求めます`,
                                            confirmText: '取り消す',
                                            onConfirm: () =>
                                                router.post('/settings/devices/trusted/revoke', { id: device.id }),
                                        })
                                    }
                                >
                                    取り消す
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
                                title: 'すべての端末の信頼を取り消しますか',
                                description: 'どの端末でも、次のログインから2段階目を求めます',
                                confirmText: 'すべて取り消す',
                                onConfirm: () => router.post('/settings/devices/trusted/revoke-all'),
                            })
                        }
                    >
                        すべての端末の信頼を取り消す
                    </Button>
                </Box>
            )}

            {dialog}
        </AppLayout>
    );
}
