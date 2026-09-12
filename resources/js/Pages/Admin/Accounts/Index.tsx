import { useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AppLayout from '../../../Components/AppLayout';
import SectionTitle from '../../../Components/SectionTitle';
import { t } from '../../../lib/i18n';
import AccountRow from './AccountRow';
import type { AdminAccount } from './types';

interface IndexProps {
    accounts: AdminAccount[];
    /** 操作している管理者のアカウントID。自分の行では操作を出さない */
    selfId: string | null;
    /** 退会したアカウントが消えるまでの日数 */
    graceDays: number;
}

/**
 * システム管理のアカウント一覧。
 *
 * 退会済みのものも出す。猶予のあいだは取り消せるので、
 * 隠すと戻せることに気付けない。
 */
export default function Index({ accounts, selfId, graceDays }: IndexProps) {
    const { accountCreated } = usePage().props.flash;
    const { errors } = usePage().props;
    const form = useForm({ email: '', display_name: '' });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.post('/admin/accounts', { onSuccess: () => form.reset() });
    };

    return (
        <AppLayout
            title={t('admin.accounts.title')}
            lead={t('admin.accounts.lead')}
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: t('admin.crumb'), href: '/admin' },
                { label: t('admin.accounts.crumb') },
            ]}
        >
            {accountCreated && (
                <Alert severity="success">
                    {t('admin.accounts.created')}
                </Alert>
            )}

            {errors.account && <Alert severity="error">{errors.account}</Alert>}

            <SectionTitle>{t('admin.accounts.create.heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Box component="form" onSubmit={submit} noValidate>
                    <Stack direction="row" spacing={1.5} sx={{ alignItems: 'flex-start', flexWrap: 'wrap' }}>
                        <TextField
                            size="small"
                            label={t('admin.accounts.fields.email')}
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            error={Boolean(form.errors.email)}
                            helperText={form.errors.email}
                        />
                        <TextField
                            size="small"
                            label={t('admin.accounts.fields.display_name')}
                            value={form.data.display_name}
                            onChange={(e) => form.setData('display_name', e.target.value)}
                            error={Boolean(form.errors.display_name)}
                            helperText={form.errors.display_name}
                        />
                        <Button type="submit" variant="contained" size="small" disabled={form.processing}>
                            {t('admin.accounts.create.submit')}
                        </Button>
                    </Stack>
                </Box>

                <Typography sx={{ mt: 1.5, fontSize: '0.8125rem', color: 'text.disabled' }}>
                    {t('admin.accounts.create.note')}
                </Typography>
            </Paper>

            <SectionTitle note={t('admin.accounts.count', { count: accounts.length })}>
                {t('admin.accounts.list.heading')}
            </SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {accounts.length === 0 && (
                        <Typography sx={{ px: 2, py: 2, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            {t('admin.accounts.list.empty')}
                        </Typography>
                    )}

                    {accounts.map((account) => (
                        <AccountRow
                            key={account.id}
                            account={account}
                            isSelf={account.id === selfId}
                            graceDays={graceDays}
                        />
                    ))}
                </Stack>
            </Paper>
        </AppLayout>
    );
}
