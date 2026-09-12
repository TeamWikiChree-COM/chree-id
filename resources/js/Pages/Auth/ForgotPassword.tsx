import { Link, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';
import TurnstileWidget from '../../Components/TurnstileWidget';
import { useTurnstilePending } from '../../lib/turnstile';
import { t } from '../../lib/i18n';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        'cf-turnstile-response': '',
    });

    // トークンが届くまで押させない。空のまま送ると必ず弾かれる
    const turnstilePending = useTurnstilePending(data['cf-turnstile-response']);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/password/forgot');
    };

    return (
        <AuthLayout
            title={t('auth.forgot_password.title')}
            heading={t('auth.forgot_password.title')}
            footer={
                <Typography variant="body2">
                    <Link href="/login">{t('auth.forgot_password.back_to_login')}</Link>
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
                        {t('auth.forgot_password.description')}
                    </Typography>

                    <TextField
                        label={t('auth.common.email_label')}
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                        autoFocus
                        required
                    />

                    <TurnstileWidget onVerify={(token) => setData('cf-turnstile-response', token)} />

                    <Button type="submit" variant="contained" disabled={processing || turnstilePending}>
                        {t('auth.forgot_password.submit')}
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
