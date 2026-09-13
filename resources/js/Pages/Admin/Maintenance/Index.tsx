import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { useConfirm } from '../../../lib/confirm';
import SectionTitle from '../../../Components/SectionTitle';
import { t } from '../../../lib/i18n';

interface Pending {
    /** 確認されないまま期限が切れた登録申し込み */
    registrations: number;
    /** 確認されないまま期限が切れたアドレス変更 */
    emailChanges: number;
    /** 使われないまま期限が切れたトークン */
    expiredTokens: number;
    /** 残す期間を過ぎた使用済みトークン */
    usedTokens: number;
    /** 猶予を過ぎた退会済みアカウント */
    withdrawnAccounts: number;
    total: number;
}

interface IndexProps {
    pending: Pending;
    /** 使用済みトークンを残す日数 */
    keepDays: number;
    /** 退会したアカウントを消すまでの日数 */
    graceDays: number;
}

/** 表に出す名前と、なぜ消してよいかの一言 */
const ROWS: { key: keyof Omit<Pending, 'total'>; label: string; note: string }[] = [
    {
        key: 'registrations',
        label: t('admin.maintenance.rows.registrations.label'),
        note: t('admin.maintenance.rows.registrations.note'),
    },
    { key: 'emailChanges', label: t('admin.maintenance.rows.email_changes.label'), note: t('admin.maintenance.rows.email_changes.note') },
    { key: 'expiredTokens', label: t('admin.maintenance.rows.expired_tokens.label'), note: t('admin.maintenance.rows.expired_tokens.note') },
    { key: 'usedTokens', label: t('admin.maintenance.rows.used_tokens.label'), note: t('admin.maintenance.rows.used_tokens.note') },
    {
        key: 'withdrawnAccounts',
        label: t('admin.maintenance.rows.withdrawn_accounts.label'),
        note: t('admin.maintenance.rows.withdrawn_accounts.note'),
    },
];

/**
 * 溜まったものの掃除。
 *
 * 日次の `chreeid:prune-tokens` と同じ処理を呼ぶ。
 * 共用サーバで cron を組めていない間の逃げ道として置いている。
 */
export default function Index({ pending, keepDays, graceDays }: IndexProps) {
    const { prunedTokens } = usePage().props.flash;
    const [running, setRunning] = useState(false);

    const { ask, dialog } = useConfirm();

    const prune = (): void => {
        ask({
            title: t('admin.maintenance.confirm.title'),
            description: t('admin.maintenance.confirm.description', { count: pending.total }),
            confirmText: t('admin.maintenance.confirm.confirm'),
            onConfirm: () =>
                router.post('/admin/maintenance/prune', {}, {
                    onStart: () => setRunning(true),
                    onFinish: () => setRunning(false),
                }),
        });
    };

    return (
        <AppLayout
            title={t('admin.maintenance.title')}
            lead={t('admin.maintenance.lead')}
            crumbs={[
                { label: t('admin.crumb'), href: '/admin' },
                { label: t('admin.maintenance.crumb') },
            ]}
        >
            {prunedTokens !== null && <Alert severity="success">{t('admin.maintenance.pruned', { count: prunedTokens })}</Alert>}

            <SectionTitle note={t('admin.maintenance.pending_count', { count: pending.total })}>{t('admin.maintenance.list.heading')}</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {ROWS.map((row) => (
                        <Box
                            key={row.key}
                            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}
                        >
                            <Box>
                                <Typography sx={{ fontSize: '0.9375rem' }}>{row.label}</Typography>
                                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>{row.note}</Typography>
                            </Box>
                            <Typography sx={{ fontSize: '0.9375rem', fontVariantNumeric: 'tabular-nums' }}>
                                {pending[row.key]}
                            </Typography>
                        </Box>
                    ))}
                </Stack>
            </Paper>

            <Box>
                <Button variant="contained" onClick={prune} disabled={running || pending.total === 0}>
                    {running ? t('admin.maintenance.running') : t('admin.maintenance.confirm.confirm')}
                </Button>
                <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>
                    {t('admin.maintenance.footer', { keep: keepDays, grace: graceDays })}
                </Typography>
            </Box>

            {dialog}
        </AppLayout>
    );
}
