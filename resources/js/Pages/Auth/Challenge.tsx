import { Head, router, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Link from '@mui/material/Link';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import type { FormEvent } from 'react';

interface ChallengeProps {
    /** 復旧コードを発行済みか。未発行なら切り替えの導線を出さない */
    hasRecoveryCodes: boolean;
}

export default function Challenge({ hasRecoveryCodes }: ChallengeProps) {
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        useRecoveryCode: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/login/challenge');
    };

    const switchMode = (toRecovery: boolean): void => {
        setUseRecoveryCode(toRecovery);
        setData({ code: '', useRecoveryCode: toRecovery });
    };

    return (
        <>
            <Head title="2段階認証" />

            <Container maxWidth="xs" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 1.5 }}>
                        <Box component="img" src="/icon.png" alt="ChreeID" sx={{ width: 32, height: 32 }} />
                        <Typography variant="h6" component="h1">
                            2段階認証
                        </Typography>
                    </Box>

                    <Paper sx={{ p: 3, border: '1px solid', borderColor: 'divider' }}>
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
                    </Paper>

                    <Typography variant="body2">
                        <Link component="button" type="button" onClick={() => router.post('/login/challenge/cancel')}>
                            ログインをやめる
                        </Link>
                    </Typography>
                </Stack>
            </Container>
        </>
    );
}
