import { Link } from '@inertiajs/react';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';
import { t } from '../../lib/i18n';

interface MagicLinkSentProps {
    /** 送信先。メールログインが使えないアドレスでもここには出す（応答を変えないため） */
    email: string;
}

export default function MagicLinkSent({ email }: MagicLinkSentProps) {
    return (
        <AuthLayout
            title={t('auth.magic_link_sent.title')}
            heading={t('auth.magic_link_sent.title')}
            footer={
                <Typography variant="body2">
                    <Link href="/login">{t('auth.magic_link.use_password')}</Link>
                </Typography>
            }
        >
            <Stack spacing={2}>
                <Typography variant="body2">{t('auth.common.sent_to', { email })}</Typography>
                <Typography variant="body2" color="text.secondary">
                    {t('auth.magic_link_sent.instructions')}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    {t('auth.magic_link_sent.not_received')}
                </Typography>
            </Stack>
        </AuthLayout>
    );
}
