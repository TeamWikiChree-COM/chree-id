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
import AppLayout from '../../Components/AppLayout';
import Icon from '../../Components/Icon';
import RowAction from '../../Components/RowAction';
import SectionTitle from '../../Components/SectionTitle';
import SettingsTabs from '../../Components/SettingsTabs';
import ToggleSwitch from '../../Components/ToggleSwitch';
import { registerPasskey } from '../../lib/passkey';
import type { CredentialSummary, CredentialTypeValue } from '../../types';

const TYPE_LABELS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'パスワード',
    magic_link: 'マジックリンク',
    totp: '認証アプリ (TOTP)',
    passkey: 'パスキー',
    oauth: '外部アカウント',
};

const TYPE_ICONS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'key',
    magic_link: 'envelope',
    totp: 'mobile-screen',
    passkey: 'fingerprint',
    oauth: 'right-to-bracket',
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

        void QRCode.toDataURL(pendingTotp, { margin: 1, width: 200 }).then(setQr);
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
    const hasMagicLink = credentials.some((c) => c.type === 'magic_link');

    return (
        <AppLayout
            title="設定"
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: '設定', href: '/settings' }, { label: 'セキュリティ' }]}
        >
            <SettingsTabs current="/settings/security" />

            <SectionTitle note={`${credentials.length}件`}>登録済みのログイン方法</SectionTitle>
            {errors.type && <Alert severity="error" sx={{ mb: 1 }}>{errors.type}</Alert>}
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {credentials.map((credential, index) => (
                        <Box
                            key={`${credential.type}-${index}`}
                            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}
                        >
                            <Box>
                                <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                    <Icon
                                        name={TYPE_ICONS[credential.type] ?? 'circle-question'}
                                        sx={{ width: 18, textAlign: 'center', color: 'text.disabled' }}
                                    />
                                    {credential.label ?? TYPE_LABELS[credential.type] ?? credential.type}
                                </Typography>
                                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                    {credential.lastUsedAt ? `最終利用 ${credential.lastUsedAt}` : '未使用'}
                                </Typography>
                            </Box>
                            <RowAction
                                destructive
                                onClick={() => router.post('/security/credentials/remove', { type: credential.type })}
                            >
                                削除
                            </RowAction>
                        </Box>
                    ))}
                </Stack>
            </Paper>

            <SectionTitle>2段階認証</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                {hasTotp && <Alert severity="success">認証アプリを設定済みです</Alert>}

                {!hasTotp && !pendingTotp && (
                    <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                            ログイン時に、認証アプリの6桁のコードを求めます
                        </Typography>
                        <Button
                            variant="outlined"
                            color="inherit"
                            // startIcon={<Icon name="mobile-screen" />}
                            onClick={() => router.post('/security/totp/start')}
                        >
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
                                totpForm.post('/security/totp/confirm');
                            }}
                        >
                            <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                                <TextField
                                    label="6桁のコード"
                                    value={totpForm.data.code}
                                    onChange={(e) => totpForm.setData('code', e.target.value)}
                                    error={Boolean(errors.code)}
                                    helperText={errors.code}
                                    slotProps={{ htmlInput: { inputMode: 'numeric' } }}
                                />
                                <Button type="submit" variant="contained" disabled={totpForm.processing}>
                                    有効にする
                                </Button>
                            </Stack>
                        </Box>
                    </Stack>
                )}
            </Paper>

            <SectionTitle>マジックリンク</SectionTitle>
            <Paper variant="outlined">
                <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}>
                    <Box>
                        <Typography sx={{ fontSize: '0.9375rem' }}>メールのリンクでログインする</Typography>
                        <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                            パスワードを使わず、届いたリンクからログインできるようにします
                        </Typography>
                    </Box>
                    <ToggleSwitch
                        checked={hasMagicLink}
                        label="マジックリンク"
                        onChange={(next) =>
                            next
                                ? router.post('/security/magic-link')
                                : router.post('/security/credentials/remove', { type: 'magic_link' })
                        }
                    />
                </Box>
            </Paper>

            <SectionTitle>パスキー</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                {passkeyError && <Alert severity="error" sx={{ mb: 1.5 }}>{passkeyError}</Alert>}

                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        端末の生体認証だけでログインできます。単独で2段階認証を満たします
                    </Typography>
                    <Button
                        variant="outlined"
                        color="inherit"
                        startIcon={<Icon name="fingerprint" />}
                        onClick={() => void addPasskey()}
                    >
                        この端末に登録する
                    </Button>
                </Stack>
            </Paper>

            <SectionTitle note={`残り ${recoveryCodeCount} 本`}>復旧コード</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                {flash.recoveryCodes && (
                    <Alert severity="warning" sx={{ mb: 1.5 }}>
                        <Typography sx={{ fontSize: '0.875rem', mb: 0.5 }}>
                            この画面を閉じると二度と表示されません
                        </Typography>
                        <Box component="pre" sx={{ m: 0, fontSize: '0.8125rem' }}>
                            {flash.recoveryCodes.join('\n')}
                        </Box>
                    </Alert>
                )}

                {hasTotp && recoveryCodeCount === 0 && (
                    <Alert severity="warning" sx={{ mb: 1.5 }}>
                        認証アプリの端末を失うとログインできなくなります。復旧コードを発行してください
                    </Alert>
                )}

                <Button
                    variant="outlined"
                    color="inherit"
                    startIcon={<Icon name="rotate" />}
                    onClick={() => router.post('/security/recovery-codes')}
                >
                    作り直す
                </Button>
            </Paper>
        </AppLayout>
    );
}
