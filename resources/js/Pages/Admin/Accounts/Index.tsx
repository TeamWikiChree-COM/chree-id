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
            title="アカウント"
            lead="登録されている ChreeID アカウントを管理します"
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: 'システム管理', href: '/admin' },
                { label: 'アカウント' },
            ]}
        >
            {accountCreated && (
                <Alert severity="success">
                    アカウントを作りました。ログイン手段は付いていないので、本人にパスワード再設定から入ってもらってください
                </Alert>
            )}

            {errors.account && <Alert severity="error">{errors.account}</Alert>}

            <SectionTitle>アカウントを作る</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Box component="form" onSubmit={submit} noValidate>
                    <Stack direction="row" spacing={1.5} sx={{ alignItems: 'flex-start', flexWrap: 'wrap' }}>
                        <TextField
                            size="small"
                            label="メールアドレス"
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            error={Boolean(form.errors.email)}
                            helperText={form.errors.email}
                        />
                        <TextField
                            size="small"
                            label="表示名"
                            value={form.data.display_name}
                            onChange={(e) => form.setData('display_name', e.target.value)}
                            error={Boolean(form.errors.display_name)}
                            helperText={form.errors.display_name}
                        />
                        <Button type="submit" variant="contained" size="small" disabled={form.processing}>
                            作成
                        </Button>
                    </Stack>
                </Box>

                <Typography sx={{ mt: 1.5, fontSize: '0.8125rem', color: 'text.disabled' }}>
                    ログイン手段は付けません。管理者が決めたパスワードは本人以外が知っている状態になるので、
                    本人にパスワード再設定かマジックリンクで入ってもらいます
                </Typography>
            </Paper>

            <SectionTitle note={`${accounts.length}件`}>アカウント一覧</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {accounts.length === 0 && (
                        <Typography sx={{ px: 2, py: 2, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            アカウントが登録されていません
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
