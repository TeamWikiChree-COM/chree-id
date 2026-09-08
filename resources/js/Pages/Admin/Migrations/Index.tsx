import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import Icon from '../../../Components/Icon';
import SectionTitle from '../../../Components/SectionTitle';

interface IndexProps {
    /** まだ適用されていないもの。古い順 */
    pending: string[];
    /** 適用済みのもの。新しい順 */
    applied: string[];
}

/**
 * データベース構造の適用。
 *
 * 本番のデプロイはファイルを置くだけなので、置いた直後は
 * 新しいコードと古い表が噛み合っていない。ここで追いつかせる。
 */
export default function Index({ pending, applied }: IndexProps) {
    const { migrationOutput } = usePage().props.flash;
    const [running, setRunning] = useState(false);

    const run = (): void => {
        if (!window.confirm(`未適用の ${pending.length} 件を適用します。データベースの構造が変わります。`)) return;

        router.post('/admin/migrations/run', {}, {
            onStart: () => setRunning(true),
            onFinish: () => setRunning(false),
        });
    };

    return (
        <AppLayout
            title="データベース構造"
            lead="デプロイで置かれたマイグレーションを適用します"
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: 'システム管理', href: '/admin' },
                { label: 'データベース構造' },
            ]}
        >
            {migrationOutput && (
                <Alert severity="success" sx={{ whiteSpace: 'pre-wrap', fontFamily: 'monospace', fontSize: '0.8125rem' }}>
                    {migrationOutput}
                </Alert>
            )}

            <SectionTitle note={pending.length === 0 ? undefined : `${pending.length}件`}>未適用</SectionTitle>
            <Paper variant="outlined">
                {pending.length === 0 ? (
                    <Typography sx={{ px: 2, py: 2, fontSize: '0.9375rem', color: 'text.disabled' }}>
                        未適用のものはありません。構造は最新です
                    </Typography>
                ) : (
                    <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                        {pending.map((name) => (
                            <Box key={name} sx={{ display: 'flex', alignItems: 'center', gap: 1.5, px: 2, py: 1.5 }}>
                                <Icon name="circle-dot" sx={{ fontSize: '0.75rem', color: 'warning.main' }} />
                                <Typography sx={{ fontSize: '0.875rem', fontFamily: 'monospace', wordBreak: 'break-all' }}>
                                    {name}
                                </Typography>
                            </Box>
                        ))}
                    </Stack>
                )}
            </Paper>

            {pending.length > 0 && (
                <Box>
                    <Button variant="contained" onClick={run} disabled={running}>
                        {running ? '適用中…' : '適用する'}
                    </Button>
                    <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>
                        前に進めるだけで、巻き戻しはできません。
                    </Typography>
                </Box>
            )}

            <SectionTitle note={`${applied.length}件`}>適用済み</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {applied.length === 0 && (
                        <Typography sx={{ px: 2, py: 2, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            まだ何も適用されていません
                        </Typography>
                    )}

                    {applied.map((name) => (
                        <Box key={name} sx={{ display: 'flex', alignItems: 'center', gap: 1.5, px: 2, py: 1.5 }}>
                            <Icon name="check" sx={{ fontSize: '0.75rem', color: 'success.main' }} />
                            <Typography
                                sx={{ fontSize: '0.875rem', fontFamily: 'monospace', color: 'text.secondary', wordBreak: 'break-all' }}
                            >
                                {name}
                            </Typography>
                        </Box>
                    ))}
                </Stack>
            </Paper>
        </AppLayout>
    );
}
