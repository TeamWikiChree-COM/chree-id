import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';

interface AuthLayoutProps {
    /** ブラウザのタイトル。カードの中には出さない */
    title: string;
    /** カードの中の見出し。省略するとサービス名だけになる */
    heading?: string;
    children: ReactNode;
    /** 枠の下に置くリンクなど */
    footer?: ReactNode;
}

/**
 * ログイン・登録まわりの画面の外枠。
 *
 * ModParks のログインに倣って、中央に1枚だけカードを置く。
 * サービス名の下に説明を置かないのは、接続先が増えるほど
 * 「何のためのアカウントか」を一言で言えなくなるため。
 */
export default function AuthLayout({ title, heading, children, footer }: AuthLayoutProps) {
    return (
        <>
            <Head title={title} />

            <Container maxWidth="xs" sx={{ py: 7 }}>
                <Paper variant="outlined" sx={{ p: 4 }}>
                    <Box sx={{ textAlign: 'center', mb: 3.5 }}>
                        <Box
                            component="img"
                            src="/icon.png"
                            alt=""
                            sx={{ width: 36, height: 36, verticalAlign: '-0.5rem', mr: 1 }}
                        />
                        <Box
                            component="span"
                            sx={{ fontSize: '1.5rem', fontWeight: 800, letterSpacing: '-0.01em' }}
                        >
                            ChreeID
                        </Box>

                        {heading !== undefined && (
                            <Typography sx={{ mt: 1, fontSize: '0.875rem', color: 'text.secondary' }}>
                                {heading}
                            </Typography>
                        )}
                    </Box>

                    <Stack spacing={2}>{children}</Stack>
                </Paper>

                {footer !== undefined && (
                    <Box sx={{ mt: 2.5, textAlign: 'center', fontSize: '0.875rem' }}>{footer}</Box>
                )}
            </Container>
        </>
    );
}
