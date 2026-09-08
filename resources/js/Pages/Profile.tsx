import { Head, router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';

interface ProfileProps {
    /** 未設定なら null */
    displayName: string | null;
    email: string | null;
    emailVerified: boolean;
}

export default function Profile({ displayName, email, emailVerified }: ProfileProps) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        display_name: displayName ?? '',
    });

    const emailForm = useForm({ email: email ?? '' });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/profile');
    };

    const submitEmail = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        emailForm.post('/profile/email/change');
    };

    return (
        <>
            <Head title="プロフィール" />

            <Container maxWidth="sm" sx={{ py: 6 }}>
                <Stack spacing={3}>
                    <Box>
                        <Typography variant="h6" component="h1">
                            プロフィール
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            連携先のサービスに渡される情報です
                        </Typography>
                    </Box>

                    {flash.profileSaved && <Alert severity="success">保存しました</Alert>}
                    {flash.verificationSent && (
                        <Alert severity="info">確認メールを送りました。本文のリンクを開いてください</Alert>
                    )}
                    {flash.emailVerified === true && (
                        <Alert severity="success">メールアドレスを確認しました</Alert>
                    )}
                    {flash.emailVerified === false && (
                        <Alert severity="error">このリンクは期限切れか、すでに使用されています</Alert>
                    )}
                    {flash.emailChangeSent && (
                        <Alert severity="info">
                            新しいアドレスに確認メールを送りました。リンクを開くまで変更されません
                        </Alert>
                    )}
                    {flash.emailChanged === true && (
                        <Alert severity="success">メールアドレスを変更しました</Alert>
                    )}
                    {flash.emailChanged === false && (
                        <Alert severity="error">このリンクは期限切れか、すでに使用されています</Alert>
                    )}

                    <Paper sx={{ p: 2, border: '1px solid', borderColor: 'divider' }}>
                        <Box component="form" onSubmit={submit} noValidate>
                            <Stack spacing={2}>
                                <TextField
                                    label="表示名"
                                    value={data.display_name}
                                    onChange={(e) => setData('display_name', e.target.value)}
                                    error={Boolean(errors.display_name)}
                                    helperText={errors.display_name ?? '任意。空にすると未設定に戻ります'}
                                    autoComplete="nickname"
                                />

                                <Box>
                                    <Button type="submit" variant="contained" disabled={processing}>
                                        保存する
                                    </Button>
                                </Box>
                            </Stack>
                        </Box>
                    </Paper>

                    <Box>
                        <Typography variant="subtitle2" gutterBottom>
                            メールアドレス
                        </Typography>

                        <Paper sx={{ p: 2, border: '1px solid', borderColor: 'divider' }}>
                            <Stack spacing={1} sx={{ alignItems: 'flex-start' }}>
                                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                                    <Typography variant="body2">{email ?? '未設定'}</Typography>
                                    <Chip
                                        size="small"
                                        label={emailVerified ? '確認済み' : '未確認'}
                                        color={emailVerified ? 'success' : 'default'}
                                    />
                                </Stack>

                                {email !== null && !emailVerified && (
                                    <>
                                        <Typography variant="body2" color="text.secondary">
                                            確認が済んでいないと、連携先のサービスへアカウントを引き継げません
                                        </Typography>
                                        <Button
                                            variant="outlined"
                                            color="inherit"
                                            onClick={() => router.post('/profile/email/verify')}
                                        >
                                            確認メールを送る
                                        </Button>
                                    </>
                                )}

                                <Box component="form" onSubmit={submitEmail} noValidate sx={{ width: '100%', pt: 1 }}>
                                    <Stack spacing={1}>
                                        <TextField
                                            label="新しいメールアドレス"
                                            type="email"
                                            value={emailForm.data.email}
                                            onChange={(e) => emailForm.setData('email', e.target.value)}
                                            error={Boolean(emailForm.errors.email)}
                                            helperText={
                                                emailForm.errors.email ??
                                                '新しいアドレスに確認メールを送ります。リンクを開くまで変更されません'
                                            }
                                        />
                                        <Box>
                                            <Button
                                                type="submit"
                                                variant="outlined"
                                                color="inherit"
                                                disabled={emailForm.processing}
                                            >
                                                変更を申し込む
                                            </Button>
                                        </Box>
                                    </Stack>
                                </Box>
                            </Stack>
                        </Paper>
                    </Box>

                    <Box>
                        <Button color="inherit" onClick={() => router.get('/')}>
                            アカウントに戻る
                        </Button>
                    </Box>
                </Stack>
            </Container>
        </>
    );
}
