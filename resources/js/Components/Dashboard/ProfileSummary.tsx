import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import { t } from '../../lib/i18n';
import type { Account } from '../../types';

interface ProfileSummaryProps {
    account: Account;
}

/**
 * ダッシュボード先頭の、本人のプロフィール概要。
 */
export default function ProfileSummary({ account }: ProfileSummaryProps) {
    return (
        <Paper variant="outlined" sx={{ p: 2 }}>
            <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 2 }}>
                <Box>
                    <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                        {account.displayName ?? t('dashboard.profile.no_display_name')}
                    </Typography>

                    <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>
                        {account.email ?? t('dashboard.profile.no_email')}
                        {account.email !== null && (
                            <Chip
                                size="small"
                                label={account.emailVerified ? t('dashboard.profile.email_verified') : t('dashboard.profile.email_unverified')}
                                color={account.emailVerified ? 'success' : 'default'}
                            />
                        )}
                    </Typography>

                    <Typography component="code" sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                        {account.id}
                    </Typography>
                </Box>

                <Button size="small" color="inherit" onClick={() => router.get('/settings')}>
                    {t('common.action.edit')}
                </Button>
            </Box>
        </Paper>
    );
}
