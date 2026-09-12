import { Link } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';
import { t } from '../../lib/i18n';

export default function MagicLinkFailed() {
    return (
        <AuthLayout
            title={t('auth.common.link_unusable')}
            heading={t('auth.common.link_unusable')}
            footer={
                <Typography variant="body2">
                    <Link href="/login/magic">{t('auth.magic_link_failed.retry')}</Link>
                </Typography>
            }
        >
            <Typography variant="body2">
                {t('auth.common.link_expired')}
            </Typography>
        </AuthLayout>
    );
}
