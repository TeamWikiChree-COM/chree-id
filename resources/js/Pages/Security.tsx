import { Head, router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemText from '@mui/material/ListItemText';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { registerPasskey } from '../lib/passkey';
import type { CredentialSummary, CredentialTypeValue } from '../types';

const TYPE_LABELS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'パスワード',
    magic_link: 'メールでログイン',
    totp: '認証アプリ (TOTP)',
    passkey: 'パスキー',
    oauth: '外部アカウント',
};

interface SecurityProps {
    credentials: CredentialSummary[];
    /** 未使用の復旧コードの残り本数 */
    recoveryCodeCount: number;
    /** TOTP 設定の途中なら otpauth:// の URI。そうでなければ null */
    pendingTotp: string | null;
}

export default function Security({ credentials, recoveryCodeCount, pendingTotp }: SecurityProps) {
    const { flash, errors } = usePage().props;
    const [qr, setQr] = useState<string | null>(null);
    const [passkeyError, setPasskeyError] = useState<string | null>(null);
    const totpForm = useForm({ code: '' });

    useEffect(() => {
        if (!pendingTotp) {
            setQr(null);

            return;
        }

        QRCode.toDataURL(pendingTotp, { margin: 1, width: 200 }).then(setQr);
    }, [pendingTotp]);

    const addPasskey = async (): Promise<void> => {
        setPasskeyError(null);
        try {
            await registerPasskey();
            router.reload();
        } catch (error) {
            setPasskeyError(error instanceof Error ? error.message : String(error));
        }
    };

    const hasTotp = credentials.some((c) => c.type === 'totp');

    return (
        <>
            <Head title="ログイン方法" />

            <Container maxWidth="sm" sx={{ py: 6 }}>
                <Stack spacing={4}>
                    <Box>
                        <Typography variant="h6" component="h1">
                            ログイン方法
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            アカウントに使える認証手段を管理します
                        </Typography>
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" gutterBottom>
                            登録済み
                        </Typography>

                        {errors.type && <Alert severity="error" sx={{ mb: 1 }}>{errors.type}</Alert>}

                        <Paper sx={{ border: '1px solid', borderColor: 'divider' }}>
                            <List dense disablePadding>
                                {credentials.map((credential, index) => (
                                    <ListItem
                                        key={`${credential.type}-${index}`}
                                        divider
                                        secondaryAction={
                                            <Button
                                                size="small"
                                                color="inherit"
                                                onClick={() => router.post('/security/credentials/remove', { type: credential.type })}
                                            >
                                                削除
                                            </Button>
                                        }
                                    >
                                        <ListItemText
                                            primary={credential.label ?? TYPE_LABELS[credential.type] ?? credential.type}
                                            secondary={credential.lastUsedAt ? `最終利用 ${credential.lastUsedAt}` : '未使用'}
                                        />
                                    </ListItem>
                                ))}
                            </List>
                        </Paper>
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" gutterBottom>
                            認証アプリ (TOTP)
                        </Typography>

                        {hasTotp && <Alert severity="success">設定済みです</Alert>}

                        {!hasTotp && !pendingTotp && (
                            <Button variant="outlined" color="inherit" onClick={() => router.post('/security/totp/start')}>
                                設定をはじめる
                            </Button>
                        )}

                        {!hasTotp && pendingTotp && (
                            <Paper sx={{ p: 2, border: '1px solid', borderColor: 'divider' }}>
                                <Stack spacing={2}>
                                    <Typography variant="body2">
                                        認証アプリで読み取り、表示された6桁を入力してください
                                    </Typography>

                                    {qr && <Box component="img" src={qr} alt="" sx={{ width: 200, alignSelf: 'center' }} />}

                                    <Box
                                        component="form"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            totpForm.post('/security/totp/confirm');
                                        }}
                                    >
                                        <Stack spacing={1}>
                                            <TextField
                                                label="6桁のコード"
                                                value={totpForm.data.code}
                                                onChange={(e) => totpForm.setData('code', e.target.value)}
                                                error={Boolean(errors.code)}
                                                helperText={errors.code}
                                                inputMode="numeric"
                                            />
                                            <Button type="submit" variant="contained" disabled={totpForm.processing}>
                                                有効にする
                                            </Button>
                                        </Stack>
                                    </Box>
                                </Stack>
                            </Paper>
                        )}
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" gutterBottom>
                            パスキー
                        </Typography>

                        {passkeyError && <Alert severity="error" sx={{ mb: 1 }}>{passkeyError}</Alert>}

                        <Button variant="outlined" color="inherit" onClick={addPasskey}>
                            この端末に登録する
                        </Button>
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" gutterBottom>
                            復旧コード
                        </Typography>

                        {flash?.recoveryCodes && (
                            <Alert severity="warning" sx={{ mb: 1 }}>
                                <Typography variant="body2" gutterBottom>
                                    この画面を閉じると二度と表示されません
                                </Typography>
                                <Box component="pre" sx={{ m: 0 }}>
                                    {flash.recoveryCodes.join('\n')}
                                </Box>
                            </Alert>
                        )}

                        <Stack spacing={1} sx={{ alignItems: 'flex-start' }}>
                            <Typography variant="body2" color="text.secondary">
                                残り {recoveryCodeCount} 本
                            </Typography>
                            <Button variant="outlined" color="inherit" onClick={() => router.post('/security/recovery-codes')}>
                                作り直す
                            </Button>
                        </Stack>
                    </Box>

                    <Box>
                        <Button color="inherit" onClick={() => router.get('/')}>
                            アカウントに戻る
                        </Button>
                    </Box>
                </Stack>
            </Container>
        </>
    );
}
