import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import Icon from '../../Components/Icon';
import SectionTitle from '../../Components/SectionTitle';
import SettingsTabs from '../../Components/SettingsTabs';
import ToggleSwitch from '../../Components/ToggleSwitch';
import CredentialList from '../../Components/Settings/CredentialList';
import PasswordSection from '../../Components/Settings/PasswordSection';
import TotpSection from '../../Components/Settings/TotpSection';
import { registerPasskey } from '../../lib/passkey';
import type { CredentialSummary } from '../../types';

interface SecurityProps {
    credentials: CredentialSummary[];
    /** パスワードを設定済みか。未設定なら現在のパスワードを聞かない */
    hasPassword: boolean;
    /** 未使用の復旧コードの残り本数 */
    recoveryCodeCount: number;
    /** TOTP 設定の途中なら otpauth:// の URI。そうでなければ null */
    pendingTotp: string | null;
}

export default function Security({ credentials, hasPassword, recoveryCodeCount, pendingTotp }: SecurityProps) {
    const { flash, errors } = usePage().props;
    const [passkeyError, setPasskeyError] = useState<string | null>(null);

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

            {flash.passwordChanged && <Alert severity="success">パスワードを変更しました</Alert>}

            <SectionTitle note={`${credentials.length}件`}>登録済みのログイン方法</SectionTitle>
            {errors.credential && <Alert severity="error" sx={{ mb: 1 }}>{errors.credential}</Alert>}
            <CredentialList credentials={credentials} />

            <SectionTitle>パスワード</SectionTitle>
            <PasswordSection hasPassword={hasPassword} />

            <SectionTitle>2段階認証</SectionTitle>
            <TotpSection hasTotp={hasTotp} pendingTotp={pendingTotp} />

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
                            router.post(next ? '/security/magic-link' : '/security/magic-link/disable')
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

                {hasTotp && recoveryCodeCount === 0 && !flash.recoveryCodes && (
                    <Alert severity="warning" sx={{ mb: 1.5 }}>
                        復旧コードを使い切っています。認証アプリの端末を失うとログインできなくなります
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
