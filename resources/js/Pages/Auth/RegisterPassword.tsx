import { useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';
import PasswordField from '../../Components/PasswordField';
import { t } from '../../lib/i18n';

interface RegisterPasswordProps {
    /** メールに載せた平文トークン。そのまま送り返す */
    token: string;
}

export default function RegisterPassword({ token }: RegisterPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        password: '',
        display_name: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/register/complete');
    };

    return (
        <AuthLayout title={t('auth.register_password.title')}
            heading={t('auth.register_password.heading')}>
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {errors.token && <Alert severity="error">{errors.token}</Alert>}

                    <Typography variant="body2" color="text.secondary">
                        {t('auth.register_password.description')}
                    </Typography>

                    <PasswordField
                        label={t('auth.common.password_label')}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={Boolean(errors.password)}
                        helperText={errors.password ?? t('auth.common.password_min_length')}
                        autoComplete="new-password"
                        autoFocus
                        required
                    />

                    <TextField
                        label={t('auth.common.display_name_label')}
                        value={data.display_name}
                        onChange={(e) => setData('display_name', e.target.value)}
                        error={Boolean(errors.display_name)}
                        helperText={errors.display_name ?? t('auth.common.display_name_hint')}
                        autoComplete="nickname"
                    />

                    <Button type="submit" variant="contained" disabled={processing}>
                        {t('auth.register_password.submit')}
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
