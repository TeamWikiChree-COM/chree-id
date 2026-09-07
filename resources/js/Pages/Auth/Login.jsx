import { Head, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="ログイン" />

            <Container maxWidth="xs" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box>
                        <Typography variant="h6" component="h1">
                            ChreeID にログイン
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            Chree 関連サービスの共通アカウント
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
                                    label="パスワード"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoComplete="current-password"
                                    required
                                />

                                <Button type="submit" variant="contained" disabled={processing}>
                                    ログイン
                                </Button>
                            </Stack>
                        </Box>
                    </Paper>
                </Stack>
            </Container>
        </>
    );
}
