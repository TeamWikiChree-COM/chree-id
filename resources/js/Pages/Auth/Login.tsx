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
import { useTurnstilePending } from '../../lib/turnstile';
import { t } from '../../lib/i18n';

interface LoginProps {
    /** サービスが login_hint で添えてきたアドレス。二度打たせないために埋める */
    email: string | null;
}

export default function Login({ email }: LoginProps) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        email: email ?? '',
        password: '',
        'cf-turnstile-response': '',
    });

    // トークンが届くまで押させない。空のまま送ると必ず弾かれる
    const turnstilePending = useTurnstilePending(data['cf-turnstile-response']);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/login');
    };

    return (
        <AuthLayout
            title={t('auth.login.title')}
            heading={t('auth.login.heading')}
            footer={
                <Stack spacing={0.5}>
                    <Box component={InertiaLink} href="/register" sx={{ color: 'primary.main' }}>
                        {t('auth.login.create_account')}
                    </Box>
                </Stack>
            }
        >
            {flash.passwordReset && (
                <Alert severity="success">{t('auth.login.password_reset_success')}</Alert>
            )}
            {errors.email && <Alert severity="error">{errors.email}</Alert>}
            {errors['cf-turnstile-response'] && <Alert severity="error">{errors['cf-turnstile-response']}</Alert>}

            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    <TextField
                        label={t('auth.common.email_label')}
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                        autoFocus
                        required
                    />

                    <Box>
                        <PasswordField
                            label={t('auth.common.password_label')}
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
                                {t('auth.login.forgot_password')}
                            </Typography>
                        </Box>
                    </Box>

                    <TurnstileWidget onVerify={(token) => setData('cf-turnstile-response', token)} />

                    <Button type="submit" variant="contained" size="large" fullWidth disabled={processing || turnstilePending}>
                        {t('auth.login.submit')}
                    </Button>
                </Stack>
            </Box>

            <Divider sx={{ my: 1 }}>
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>{t('auth.common.or')}</Typography>
            </Divider>

            <SocialLogins magicLinkHref="/login/magic" />
        </AuthLayout>
    );
}
