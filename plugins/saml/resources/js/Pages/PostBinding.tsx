import { useEffect, useRef } from 'react';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AuthLayout from '@/Components/AuthLayout';
import { t } from '../lib/i18n';

interface PostBindingProps {
    /** SP の ACS */
    action: string;
    /** SAMLResponse と RelayState */
    fields: Record<string, string>;
}

/**
 * 署名した SAML Response を SP へ POST する (HTTP-POST バインディング)。
 *
 * 開いたらすぐ送る。スクリプトが動かない環境向けにボタンも置く。
 */
export default function PostBinding({ action, fields }: PostBindingProps) {
    const form = useRef<HTMLFormElement>(null);

    useEffect(() => {
        form.current?.submit();
    }, []);

    return (
        <AuthLayout title={t('post.title')} heading={t('post.title')}>
            <Stack spacing={2} component="form" method="post" action={action} ref={form}>
                {Object.entries(fields).map(([name, value]) => (
                    <input key={name} type="hidden" name={name} value={value} />
                ))}
                <Typography variant="body2" color="text.secondary">
                    {t('post.lead')}
                </Typography>
                <Button type="submit" variant="contained">
                    {t('post.continue')}
                </Button>
            </Stack>
        </AuthLayout>
    );
}
