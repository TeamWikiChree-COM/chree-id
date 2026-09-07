import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export default function Home({ issuer }) {
    return (
        <>
            <Head title="ChreeID" />

            <Container maxWidth="sm" sx={{ py: 6 }}>
                <Stack spacing={3}>
                    <Box>
                        <Typography variant="h5" component="h1">
                            ChreeID
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            Chree 関連サービスの共通認証基盤
                        </Typography>
                    </Box>

                    <Paper sx={{ p: 2, border: '1px solid', borderColor: 'divider' }}>
                        <Typography variant="body2" color="text.secondary">
                            issuer
                        </Typography>
                        <Typography variant="body1" component="code">
                            {issuer}
                        </Typography>
                    </Paper>
                </Stack>
            </Container>
        </>
    );
}
