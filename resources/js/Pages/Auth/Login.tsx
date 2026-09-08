import { Link, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';
import TurnstileWidget from '../../Components/TurnstileWidget';

export default function Login() {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        'cf-turnstile-response': '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/login');
    };

    return (
        <AuthLayout
            title="ChreeID にログイン"
            footer={
                <Stack spacing={0.5}>
                    <Typography variant="body2">
                        <Link href="/password/forgot">パスワードをお忘れですか？</Link>
                    </Typography>
                    <Typography variant="body2">
                        <Link href="/register">アカウントを作成する</Link>
                    </Typography>
                </Stack>
            }
        >
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {flash.passwordReset && (
                        <Alert severity="success">パスワードを変更しました。新しいパスワードでログインしてください</Alert>
                    )}
                    {errors.email && <Alert severity="error">{errors.email}</Alert>}
                    {errors['cf-turnstile-response'] && (
                        <Alert severity="error">{errors['cf-turnstile-response']}</Alert>
                    )}

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

                    <TurnstileWidget onVerify={(token) => setData('cf-turnstile-response', token)} />

                    <Button type="submit" variant="contained" disabled={processing}>
                        ログイン
                    </Button>

                    <Divider>または</Divider>

                    <Button component="a" href="/auth/google/redirect" variant="outlined" color="inherit">
                        Google で続行
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
