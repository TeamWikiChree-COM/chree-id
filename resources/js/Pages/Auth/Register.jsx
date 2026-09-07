import { Head, Link, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        display_name: '',
        password: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/register');
    };

    return (
        <>
            <Head title="アカウント登録" />

            <Container maxWidth="xs" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 1.5 }}>
                        <Box component="img" src="/icon.png" alt="ChreeID" sx={{ width: 32, height: 32 }} />
                        <Typography variant="h6" component="h1">
                            ChreeID を新規作成
                        </Typography>
                    </Box>

                    <Paper sx={{ p: 3, border: '1px solid', borderColor: 'divider' }}>
                        <Box component="form" onSubmit={submit} noValidate>
                            <Stack spacing={2}>
                                {errors.email && <Alert severity="error">{errors.email}</Alert>}

                                <TextField
                                    label="メールアドレス"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    autoComplete="username"
                                    autoFocus
                                    required
                                />

                                <TextField
                                    label="表示名"
                                    value={data.display_name}
                                    onChange={(e) => setData('display_name', e.target.value)}
                                    error={Boolean(errors.display_name)}
                                    helperText={errors.display_name}
                                    required
                                />

                                <TextField
                                    label="パスワード"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    error={Boolean(errors.password)}
                                    helperText={errors.password ?? '8文字以上'}
                                    autoComplete="new-password"
                                    required
                                />

                                <Button type="submit" variant="contained" disabled={processing}>
                                    作成する
                                </Button>

                                <Divider>または</Divider>

                                <Button component="a" href="/federation/google/redirect" variant="outlined" color="inherit">
                                    Google で続行
                                </Button>
                            </Stack>
                        </Box>
                    </Paper>

                    <Typography variant="body2">
                        <Link href="/login">アカウントをお持ちの方はこちら</Link>
                    </Typography>
                </Stack>
            </Container>
        </>
    );
}
