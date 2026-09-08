import { Head, router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
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
}

export default function Profile({ displayName, email }: ProfileProps) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        display_name: displayName ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/profile');
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
                                    <Typography variant="body2" color="text.secondary">
                                        メールアドレス
                                    </Typography>
                                    <Typography variant="body2">{email ?? '未設定'}</Typography>
                                </Box>

                                <Box>
                                    <Button type="submit" variant="contained" disabled={processing}>
                                        保存する
                                    </Button>
                                </Box>
                            </Stack>
                        </Box>
                    </Paper>

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
