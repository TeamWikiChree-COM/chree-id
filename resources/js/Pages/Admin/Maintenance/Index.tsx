import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import SectionTitle from '../../../Components/SectionTitle';

interface Pending {
    /** 確認されないまま期限が切れた登録申し込み */
    registrations: number;
    /** 確認されないまま期限が切れたアドレス変更 */
    emailChanges: number;
    /** 使われないまま期限が切れたトークン */
    expiredTokens: number;
    /** 残す期間を過ぎた使用済みトークン */
    usedTokens: number;
    total: number;
}

interface IndexProps {
    pending: Pending;
    /** 使用済みトークンを残す日数 */
    keepDays: number;
}

/** 表に出す名前と、なぜ消してよいかの一言 */
const ROWS: { key: keyof Omit<Pending, 'total'>; label: string; note: string }[] = [
    {
        key: 'registrations',
        label: '期限切れの登録申し込み',
        note: 'パスワードハッシュを持つので、期限が切れたら残す理由がない',
    },
    { key: 'emailChanges', label: '期限切れのアドレス変更', note: 'リンクは既に使えない' },
    { key: 'expiredTokens', label: '未使用のまま期限切れ', note: '使い捨てトークン' },
    { key: 'usedTokens', label: '使用済みトークン', note: '二重投入の検知に使うので少し残してある' },
];

/**
 * 溜まったものの掃除。
 *
 * 日次の `chreeid:prune-tokens` と同じ処理を呼ぶ。
 * 共用サーバで cron を組めていない間の逃げ道として置いている。
 */
export default function Index({ pending, keepDays }: IndexProps) {
    const { prunedTokens } = usePage().props.flash;
    const [running, setRunning] = useState(false);

    const prune = (): void => {
        if (!window.confirm(`${String(pending.total)} 件を削除します。`)) return;

        router.post('/admin/maintenance/prune', {}, {
            onStart: () => setRunning(true),
            onFinish: () => setRunning(false),
        });
    };

    return (
        <AppLayout
            title="掃除"
            lead="期限切れの申し込みと使い捨てトークンを削除します"
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: 'システム管理', href: '/admin' },
                { label: '掃除' },
            ]}
        >
            {prunedTokens !== null && <Alert severity="success">{prunedTokens} 件を削除しました</Alert>}

            <SectionTitle note={`${pending.total}件`}>削除される対象</SectionTitle>
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
                    {running ? '削除中…' : '削除する'}
                </Button>
                <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>
                    使用済みトークンは {keepDays} 日ぶん残します。日次の chreeid:prune-tokens と同じ処理です
                </Typography>
            </Box>
        </AppLayout>
    );
}
