import { router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import InertiaLink from '../../Components/InertiaLink';
import Icon from '../../Components/Icon';
import SectionTitle from '../../Components/SectionTitle';
import SettingsTabs from '../../Components/SettingsTabs';

interface ProfileProps {
    /** 未設定なら null */
    displayName: string | null;
    email: string | null;
    emailVerified: boolean;
}

export default function Profile({ displayName, email, emailVerified }: ProfileProps) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ display_name: displayName ?? '' });
    // 現在のアドレスは上に出ている。ここに入れておくと、そのまま送信して
    // 「確認メールを送りました」が出るのに何も変わらない
    const emailForm = useForm({ email: '' });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/profile');
    };

    const submitEmail = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        emailForm.post('/profile/email/change', { onSuccess: () => emailForm.reset() });
    };

    return (
        <AppLayout
            title="設定"
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: '設定' }]}
        >
            <SettingsTabs current="/settings" />

            <Stack spacing={1.5}>
                {flash.profileSaved && <Alert severity="success">保存しました</Alert>}
                {flash.verificationSent && (
                    <Alert severity="info">確認メールを送りました。本文のリンクを開いてください</Alert>
                )}
                {flash.emailVerified === true && <Alert severity="success">メールアドレスを確認しました</Alert>}
                {flash.emailVerified === false && (
                    <Alert severity="error">このリンクは期限切れか、すでに使用されています</Alert>
                )}
                {flash.emailChangeSent && (
                    <Alert severity="info">新しいアドレスに確認メールを送りました。リンクを開くまで変更されません</Alert>
                )}
                {flash.emailChanged === true && <Alert severity="success">メールアドレスを変更しました</Alert>}
                {flash.emailChanged === false && (
                    <Alert severity="error">このリンクは期限切れか、すでに使用されています</Alert>
                )}
            </Stack>

            <SectionTitle>表示名</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Box component="form" onSubmit={submit} noValidate>
                    <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                        <TextField
                            label="表示名"
                            value={data.display_name}
                            onChange={(e) => setData('display_name', e.target.value)}
                            error={Boolean(errors.display_name)}
                            helperText={errors.display_name ?? '任意。空にすると未設定に戻ります'}
                            autoComplete="nickname"
                        />
                        <Button type="submit" variant="contained" disabled={processing}>
                            保存する
                        </Button>
                    </Stack>
                </Box>
            </Paper>

            <SectionTitle>メールアドレス</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <Typography sx={{ fontSize: '0.9375rem' }}>{email ?? '未設定'}</Typography>
                        <Chip
                            size="small"
                            label={emailVerified ? '確認済み' : '未確認'}
                            color={emailVerified ? 'success' : 'default'}
                        />
                    </Stack>

                    {email !== null && !emailVerified && (
                        <>
                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                確認が済んでいないと、連携先のサービスへアカウントを引き継げません
                            </Typography>
                            <Button
                                variant="outlined"
                                color="inherit"
                                startIcon={<Icon name="envelope" />}
                                onClick={() => router.post('/profile/email/verify')}
                            >
                                確認メールを送る
                            </Button>
                        </>
                    )}

                    <Box component="form" onSubmit={submitEmail} noValidate sx={{ width: '100%', pt: 1 }}>
                        <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                            <TextField
                                label="新しいメールアドレス"
                                placeholder="変更後のアドレスを入力"
                                type="email"
                                value={emailForm.data.email}
                                onChange={(e) => emailForm.setData('email', e.target.value)}
                                error={Boolean(emailForm.errors.email)}
                                helperText={
                                    emailForm.errors.email ??
                                    '新しいアドレスに確認メールを送ります。リンクを開くまで変更されません'
                                }
                            />
                            <Button type="submit" variant="outlined" color="inherit" disabled={emailForm.processing}>
                                確認メールを送信する
                            </Button>
                        </Stack>
                    </Box>
                </Stack>
            </Paper>

            <SectionTitle>退会</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        連携しているサービスのアカウントも使えなくなります
                    </Typography>
                    <Button component={InertiaLink} href="/settings/withdraw" variant="outlined" color="error">
                        退会の手続きへ
                    </Button>
                </Stack>
            </Paper>
        </AppLayout>
    );
}
