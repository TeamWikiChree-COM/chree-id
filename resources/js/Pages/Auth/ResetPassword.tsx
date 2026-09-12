import { Link, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';
import PasswordField from '../../Components/PasswordField';
import { t } from '../../lib/i18n';

interface ResetPasswordProps {
    /** メールに載せた平文トークン。そのまま送り返す */
    token: string;
}

export default function ResetPassword({ token }: ResetPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        password: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/password/reset');
    };

    return (
        <AuthLayout
            title={t('auth.reset_password.title')}
            heading={t('auth.reset_password.heading')}
            footer={
                <Typography variant="body2">
                    <Link href="/login">{t('auth.forgot_password.back_to_login')}</Link>
                </Typography>
            }
        >
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {errors.token && <Alert severity="error">{errors.token}</Alert>}

                    <PasswordField
                        label={t('auth.reset_password.new_password_label')}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={Boolean(errors.password)}
                        helperText={errors.password ?? t('auth.common.password_min_length')}
                        autoComplete="new-password"
                        autoFocus
                        required
                    />

                    <Button type="submit" variant="contained" disabled={processing}>
                        {t('auth.reset_password.submit')}
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
