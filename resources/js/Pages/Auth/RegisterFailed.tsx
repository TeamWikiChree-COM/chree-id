import { Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

interface RegisterFailedProps {
    /** 理由ごとにサーバ側で選んだ文言 */
    message: string;
}

export default function RegisterFailed({ message }: RegisterFailedProps) {
    return (
        <>
            <Head title="登録を完了できません" />

            <Container maxWidth="xs" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 1.5 }}>
                        <Box component="img" src="/icon.png" alt="ChreeID" sx={{ width: 32, height: 32 }} />
                        <Typography variant="h6" component="h1">
                            登録を完了できません
                        </Typography>
                    </Box>

                    <Paper sx={{ p: 3, border: '1px solid', borderColor: 'divider' }}>
                        <Typography variant="body2">{message}</Typography>
                    </Paper>

                    <Typography variant="body2">
                        <Link href="/register">登録をやり直す</Link>
                    </Typography>
                </Stack>
            </Container>
        </>
    );
}
