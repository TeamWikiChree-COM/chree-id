import { Link } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';
import { t } from '../../lib/i18n';

interface RegisterFailedProps {
    /** 理由ごとにサーバ側で選んだ文言 */
    message: string;
}

export default function RegisterFailed({ message }: RegisterFailedProps) {
    return (
        <AuthLayout
            title={t('auth.register_failed.title')}
            heading={t('auth.register_failed.title')}
            footer={
                <Typography variant="body2">
                    <Link href="/register">{t('auth.register_failed.retry')}</Link>
                </Typography>
            }
        >
            <Typography variant="body2">{message}</Typography>
        </AuthLayout>
    );
}
