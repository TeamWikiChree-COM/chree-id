import { router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { t } from '../../lib/i18n';

interface TotpSectionProps {
    /** 設定済みか */
    hasTotp: boolean;
    /** 設定の途中なら otpauth:// の URI。そうでなければ null */
    pendingTotp: string | null;
}

/**
 * 認証アプリ (TOTP) の設定。
 *
 * QR は秘密鍵そのものなので、確認が済むまでの間しか出さない。
 */
export default function TotpSection({ hasTotp, pendingTotp }: TotpSectionProps) {
    const { flash, errors } = usePage().props;
    const [qr, setQr] = useState<string | null>(null);
    const form = useForm({ code: '' });

    useEffect(() => {
        if (!pendingTotp) {
            setQr(null);

            return;
        }

        void QRCode.toDataURL(pendingTotp, { margin: 1, width: 200 }).then(setQr);
    }, [pendingTotp]);

    return (
        <Paper variant="outlined" sx={{ p: 2 }}>
            {hasTotp && !flash.recoveryCodes && <Alert severity="success">{t('settings.totp.configured')}</Alert>}

            {hasTotp && flash.recoveryCodes && (
                <Alert severity="success">
                    {t('settings.totp.configured_with_recovery')}
                </Alert>
            )}

            {!hasTotp && !pendingTotp && (
                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('settings.totp.intro')}
                    </Typography>
                    <Button variant="outlined" color="inherit" onClick={() => router.post('/security/totp/start')}>
                        {t('settings.totp.start')}
                    </Button>
                </Stack>
            )}

            {!hasTotp && pendingTotp && (
                <Stack spacing={2}>
                    <Typography sx={{ fontSize: '0.875rem' }}>
                        {t('settings.totp.scan_instruction')}
                    </Typography>

                    {qr && <Box component="img" src={qr} alt="" sx={{ width: 200, alignSelf: 'center' }} />}

                    <Box
                        component="form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/security/totp/confirm');
                        }}
                    >
                        <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                            <TextField
                                label={t('settings.totp.code_label')}
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                error={Boolean(errors.code)}
                                helperText={errors.code}
                                slotProps={{ htmlInput: { inputMode: 'numeric' } }}
                            />
                            <Button type="submit" variant="contained" disabled={form.processing}>
                                {t('settings.totp.enable')}
                            </Button>
                        </Stack>
                    </Box>
                </Stack>
            )}
        </Paper>
    );
}
