import { useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';
import InertiaLink from '../../Components/InertiaLink';
import PasswordField from '../../Components/PasswordField';
import SocialLogins from '../../Components/SocialLogins';
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
            title="ログイン"
            heading="ChreeID にログイン"
            footer={
                <Stack spacing={0.5}>
                    <Box component={InertiaLink} href="/register" sx={{ color: 'primary.main' }}>
                        アカウントを作成する
                    </Box>
                </Stack>
            }
        >
            {flash.passwordReset && (
                <Alert severity="success">パスワードを変更しました。新しいパスワードでログインしてください</Alert>
            )}
            {errors.email && <Alert severity="error">{errors.email}</Alert>}
            {errors['cf-turnstile-response'] && <Alert severity="error">{errors['cf-turnstile-response']}</Alert>}

            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    <TextField
                        label="メールアドレス"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                        autoFocus
                        required
                    />

                    <Box>
                        <PasswordField
                            label="パスワード"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                            required
                        />
                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 0.5 }}>
                            <Typography
                                component={InertiaLink}
                                href="/password/forgot"
                                sx={{ fontSize: '0.8125rem', color: 'primary.main', textDecoration: 'none' }}
                            >
                                パスワードをお忘れですか？
                            </Typography>
                        </Box>
                    </Box>

                    <TurnstileWidget onVerify={(token) => setData('cf-turnstile-response', token)} />

                    <Button type="submit" variant="contained" size="large" fullWidth disabled={processing}>
                        ログイン
                    </Button>
                </Stack>
            </Box>

            <Divider sx={{ my: 1 }}>
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>または</Typography>
            </Divider>

            <SocialLogins magicLinkHref="/login/magic" />
        </AuthLayout>
    );
}
