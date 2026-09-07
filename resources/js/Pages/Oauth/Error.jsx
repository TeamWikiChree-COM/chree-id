import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export default function OauthError({ error, reason }) {
    return (
        <>
            <Head title="連携できません" />

            <Container maxWidth="sm" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box>
                        <Typography variant="h6" component="h1">
                            連携できません
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            サービス側の設定に問題があります。接続元のサービスにお問い合わせください。
                        </Typography>
                    </Box>

                    <Paper sx={{ p: 2, border: '1px solid', borderColor: 'divider' }}>
                        <Typography variant="body2" color="text.secondary">
                            {error}
                        </Typography>
                        <Typography variant="body1">{reason}</Typography>
                    </Paper>
                </Stack>
            </Container>
        </>
    );
}
