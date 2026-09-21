import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '../../../Components/AppLayout';
import SectionTitle from '../../../Components/SectionTitle';
import { useConfirm } from '../../../lib/confirm';
import { t } from '../../../lib/i18n';
import AccountRow from './AccountRow';
import IssueChips from './IssueChips';
import type { AdminAccount, AdminAccountIssue } from './types';

interface IssuesProps {
    accounts: (AdminAccount & { issues: AdminAccountIssue[] })[];
    selfId: string | null;
    graceDays: number;
}

/**
 * 種別と実体が食い違ったアカウント。どれも昇格で直るので、まとめて直せるようにしておく。
 */
export default function Issues({ accounts, selfId, graceDays }: IssuesProps) {
    const { errors } = usePage().props;
    const { ask, dialog } = useConfirm();

    const fixAll = (): void => {
        ask({
            title: t('admin.accounts.issues.fix_all.title'),
            description: t('admin.accounts.issues.fix_all.description', { count: accounts.length }),
            confirmText: t('admin.accounts.issues.fix_all.confirm'),
            onConfirm: () => router.post('/admin/accounts/issues/fix'),
        });
    };

    return (
        <AppLayout
            title={t('admin.accounts.issues.title')}
            lead={t('admin.accounts.issues.lead')}
            crumbs={[
                { label: t('admin.crumb'), href: '/admin' },
                { label: t('admin.accounts.crumb'), href: '/admin/accounts' },
                { label: t('admin.accounts.issues.crumb') },
            ]}
        >
            {errors.account && <Alert severity="error">{errors.account}</Alert>}

            <SectionTitle note={t('admin.accounts.count', { count: accounts.length })}>
                {t('admin.accounts.issues.heading')}
            </SectionTitle>

            {accounts.length > 0 && (
                <Box sx={{ mb: 1.5 }}>
                    <Button variant="contained" size="small" onClick={fixAll}>
                        {t('admin.accounts.issues.fix_all.confirm')}
                    </Button>
                </Box>
            )}

            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {accounts.length === 0 && (
                        <Typography sx={{ px: 2, py: 2, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            {t('admin.accounts.issues.empty')}
                        </Typography>
                    )}

                    {accounts.map((account) => (
                        <Box key={account.id}>
                            <Box sx={{ px: 2, pt: 1.5 }}>
                                <IssueChips issues={account.issues} />
                            </Box>
                            <AccountRow account={account} isSelf={account.id === selfId} graceDays={graceDays} />
                        </Box>
                    ))}
                </Stack>
            </Paper>
            {dialog}
        </AppLayout>
    );
}
