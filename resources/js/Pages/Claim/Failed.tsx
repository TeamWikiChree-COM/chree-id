import { Link } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';
import { t } from '../../lib/i18n';

interface ClaimFailedProps {
    /** 理由ごとにサーバ側で選んだ文言 */
    message: string;
}

export default function ClaimFailed({ message }: ClaimFailedProps) {
    return (
        <AuthLayout
            title={t('claim.failed.title')}
            heading={t('claim.failed.title')}
            footer={
                <Typography variant="body2">
                    <Link href="/login">{t('claim.failed.login')}</Link>
                </Typography>
            }
        >
            <Typography variant="body2">{message}</Typography>
        </AuthLayout>
    );
}
