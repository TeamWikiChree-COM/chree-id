import { Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

interface RegisterSentProps {
    /** 送信先。既に登録済みのアドレスでもここには出す（応答を変えないため） */
    email: string;
}

export default function RegisterSent({ email }: RegisterSentProps) {
    return (
        <>
            <Head title="確認メールを送りました" />

            <Container maxWidth="xs" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 1.5 }}>
                        <Box component="img" src="/icon.png" alt="ChreeID" sx={{ width: 32, height: 32 }} />
                        <Typography variant="h6" component="h1">
                            確認メールを送りました
                        </Typography>
                    </Box>

                    <Paper sx={{ p: 3, border: '1px solid', borderColor: 'divider' }}>
                        <Stack spacing={2}>
                            <Typography variant="body2">{email} 宛にメールを送りました。</Typography>
                            <Typography variant="body2" color="text.secondary">
                                本文のリンクを開くとアカウントが作られ、そのままログインします。
                                リンクを開くまでアカウントは作られません。
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                                届かない場合は、迷惑メールに振り分けられていないかご確認ください。
                            </Typography>
                        </Stack>
                    </Paper>

                    <Typography variant="body2">
                        <Link href="/login">ログイン画面に戻る</Link>
                    </Typography>
                </Stack>
            </Container>
        </>
    );
}
