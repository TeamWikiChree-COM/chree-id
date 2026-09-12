import { Link } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';
import { t } from '../../lib/i18n';

export default function ResetPasswordFailed() {
    return (
        <AuthLayout
            title={t('auth.common.link_unusable')}
            heading={t('auth.common.link_unusable')}
            footer={
                <Typography variant="body2">
                    <Link href="/password/forgot">{t('auth.reset_password_failed.retry')}</Link>
                </Typography>
            }
        >
            <Typography variant="body2">
                {t('auth.common.link_expired')}
            </Typography>
        </AuthLayout>
    );
}
