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
            title="2段階認証"
            heading="2段階認証"
            footer={
                <Typography variant="body2">
                    <Link component="button" type="button" onClick={() => router.post('/login/challenge/cancel')}>
                        ログインをやめる
                    </Link>
                </Typography>
            }
        >
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {errors.code && <Alert severity="error">{errors.code}</Alert>}

                    <Typography variant="body2" color="text.secondary">
                        {useRecoveryCode
                            ? '控えておいた復旧コードを1つ入力してください'
                            : '認証アプリに表示されている6桁を入力してください'}
                    </Typography>

                    <TextField
                        label={useRecoveryCode ? '復旧コード' : '6桁のコード'}
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
                                この端末を記憶する (次回から{String(TRUST_DAYS)}日間は省略)
                            </Typography>
                        }
                    />

                    <Button type="submit" variant="contained" disabled={processing}>
                        確認する
                    </Button>

                    {hasRecoveryCodes && (
                        <Typography variant="body2">
                            <Link component="button" type="button" onClick={() => switchMode(!useRecoveryCode)}>
                                {useRecoveryCode ? '認証アプリのコードを使う' : '認証アプリを使えない場合'}
                            </Link>
                        </Typography>
                    )}
                </Stack>
            </Box>
        </AuthLayout>
    );
}
