import { Link, useForm } from '@inertiajs/react';
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

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        'cf-turnstile-response': '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/register');
    };

    return (
        <AuthLayout
            title="ChreeID を新規作成"
            footer={
                <Typography variant="body2">
                    <Link href="/login">アカウントをお持ちの方はこちら</Link>
                </Typography>
            }
        >
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {errors.email && <Alert severity="error">{errors.email}</Alert>}
                    {errors['cf-turnstile-response'] && (
                        <Alert severity="error">{errors['cf-turnstile-response']}</Alert>
                    )}

                    <Typography variant="body2" color="text.secondary">
                        アカウント作成の確認メールを送信します
                    </Typography>

                    <TextField
                        label="メールアドレス"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                        autoFocus
                        required
                    />

                    <TurnstileWidget onVerify={(token) => setData('cf-turnstile-response', token)} />

                    <Button type="submit" variant="contained" disabled={processing}>
                        メールを送信する
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
