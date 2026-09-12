import { Link } from '@inertiajs/react';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';
import { t } from '../../lib/i18n';

interface ForgotPasswordSentProps {
    /** 送信先。登録されていないアドレスでもここには出す（応答を変えないため） */
    email: string;
}

export default function ForgotPasswordSent({ email }: ForgotPasswordSentProps) {
    return (
        <AuthLayout
            title={t('auth.forgot_password_sent.title')}
            heading={t('auth.forgot_password_sent.title')}
            footer={
                <Typography variant="body2">
                    <Link href="/login">{t('auth.forgot_password.back_to_login')}</Link>
                </Typography>
            }
        >
            <Stack spacing={2}>
                <Typography variant="body2">{t('auth.common.sent_to', { email })}</Typography>
                <Typography variant="body2" color="text.secondary">
                    {t('auth.forgot_password_sent.instructions')}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    {t('auth.common.check_spam')}
                </Typography>
            </Stack>
        </AuthLayout>
    );
}
