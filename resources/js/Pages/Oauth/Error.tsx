import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { t } from '../../lib/i18n';

interface OauthErrorProps {
    /** OAuth のエラーコード (invalid_request など) */
    error: string;
    /** 何が起きたかの説明 */
    reason: string;
}

export default function OauthError({ error, reason }: OauthErrorProps) {
    return (
        <>
            <Head title={t('oauth.error.title')} />

            <Container maxWidth="sm" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box>
                        <Typography variant="h6" component="h1">
                            {t('oauth.error.title')}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            {t('oauth.error.description')}
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
