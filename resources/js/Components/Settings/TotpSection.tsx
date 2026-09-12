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
            {hasTotp && !flash.recoveryCodes && <Alert severity="success">認証アプリを設定済みです</Alert>}

            {hasTotp && flash.recoveryCodes && (
                <Alert severity="success">
                    認証アプリを設定しました。下に復旧コードを発行しているので控えてください
                </Alert>
            )}

            {!hasTotp && !pendingTotp && (
                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        ログイン時に、認証アプリの6桁のコードを求めます
                    </Typography>
                    <Button variant="outlined" color="inherit" onClick={() => router.post('/security/totp/start')}>
                        設定をはじめる
                    </Button>
                </Stack>
            )}

            {!hasTotp && pendingTotp && (
                <Stack spacing={2}>
                    <Typography sx={{ fontSize: '0.875rem' }}>
                        認証アプリで読み取り、表示された6桁を入力してください
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
                                label="6桁のコード"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                error={Boolean(errors.code)}
                                helperText={errors.code}
                                slotProps={{ htmlInput: { inputMode: 'numeric' } }}
                            />
                            <Button type="submit" variant="contained" disabled={form.processing}>
                                有効にする
                            </Button>
                        </Stack>
                    </Box>
                </Stack>
            )}
        </Paper>
    );
}
