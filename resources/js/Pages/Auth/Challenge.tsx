import { router, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';
import { t } from '../../lib/i18n';

/** 信頼を保つ日数。PHP の TrustedDevices::LIFETIME_DAYS と合わせる */
const TRUST_DAYS = 30;

interface ChallengeProps {
    /** 復旧コードを発行済みか。未発行なら切り替えの導線を出さない */
    hasRecoveryCodes: boolean;
}

export default function Challenge({ hasRecoveryCodes }: ChallengeProps) {
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        useRecoveryCode: false,
        trustDevice: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/login/challenge');
    };

    const switchMode = (toRecovery: boolean): void => {
        setUseRecoveryCode(toRecovery);
        setData({ code: '', useRecoveryCode: toRecovery, trustDevice: data.trustDevice });
    };

    return (
        <AuthLayout
            title={t('auth.challenge.title')}
            heading={t('auth.challenge.title')}
            footer={
                <Typography variant="body2">
                    <Link component="button" type="button" onClick={() => router.post('/login/challenge/cancel')}>
                        {t('auth.challenge.cancel')}
                    </Link>
                </Typography>
            }
        >
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {errors.code && <Alert severity="error">{errors.code}</Alert>}

                    <Typography variant="body2" color="text.secondary">
                        {useRecoveryCode
                            ? t('auth.challenge.recovery_prompt')
                            : t('auth.challenge.totp_prompt')}
                    </Typography>

                    <TextField
                        label={useRecoveryCode ? t('auth.challenge.recovery_label') : t('auth.challenge.code_label')}
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        inputMode={useRecoveryCode ? 'text' : 'numeric'}
                        autoComplete="one-time-code"
                        autoFocus
                        required
                    />

                    {/* 記憶するのはこの画面を通したときだけ。ここ以外で聞いてはいけない */}
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={data.trustDevice}
                                onChange={(e) => setData('trustDevice', e.target.checked)}
                            />
                        }
                        label={
                            <Typography variant="body2">
                                {t('auth.challenge.trust_device', { days: TRUST_DAYS })}
                            </Typography>
                        }
                    />

                    <Button type="submit" variant="contained" disabled={processing}>
                        {t('auth.challenge.submit')}
                    </Button>

                    {hasRecoveryCodes && (
                        <Typography variant="body2">
                            <Link component="button" type="button" onClick={() => switchMode(!useRecoveryCode)}>
                                {useRecoveryCode ? t('auth.challenge.use_totp') : t('auth.challenge.use_recovery')}
                            </Link>
                        </Typography>
                    )}
                </Stack>
            </Box>
        </AuthLayout>
    );
}
