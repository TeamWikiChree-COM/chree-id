import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';

interface AuthLayoutProps {
    /** ブラウザのタイトルと、ロゴの横に出す見出しを兼ねる */
    title: string;
    /** 枠の中身 */
    children: ReactNode;
    /** 枠の下に置くリンクなど */
    footer?: ReactNode;
}

/**
 * ログイン・登録まわりの画面の外枠。
 *
 * ロゴ、見出し、枠線付きの Paper までを揃える。
 * 画面ごとに sx を書いて回ると必ずズレるので、ここに固定する。
 */
export default function AuthLayout({ title, children, footer }: AuthLayoutProps) {
    return (
        <>
            <Head title={title} />

            <Container maxWidth="xs" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 1.5 }}>
                        <Box component="img" src="/icon.png" alt="ChreeID" sx={{ width: 32, height: 32 }} />
                        <Typography variant="h6" component="h1">
                            {title}
                        </Typography>
                    </Box>

                    <Paper sx={{ p: 3, border: '1px solid', borderColor: 'divider' }}>{children}</Paper>

                    {footer}
                </Stack>
            </Container>
        </>
    );
}
