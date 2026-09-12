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
import CredentialList from '../../Components/CredentialList';
import PasswordSection from '../../Components/Settings/PasswordSection';
import TotpSection from '../../Components/Settings/TotpSection';
import RecoveryCodes from '../../Components/Settings/RecoveryCodes';
import RenameCredentialDialog from '../../Components/Settings/RenameCredentialDialog';
import { useConfirm } from '../../lib/confirm';
import { registerPasskey } from '../../lib/passkey';
import { credentialLabel } from '../../lib/credentials';
import { t } from '../../lib/i18n';
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
    const [renaming, setRenaming] = useState<CredentialSummary | null>(null);
    const { ask, dialog } = useConfirm();

    // 消せる = 他に入る手段が残っている、ということ。残り1件はサーバ側が弾く
    const confirmRemove = (credential: CredentialSummary): void => {
        ask({
            title: t('settings.security.credentials.remove.title'),
            description: t('settings.security.credentials.remove.description', { label: credentialLabel(credential) }),
            onConfirm: () => router.post('/security/credentials/remove', { id: credential.id }),
        });
    };

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
    const passkeys = credentials.filter((c) => c.type === 'passkey');
    const hasMagicLink = credentials.some((c) => c.type === 'magic_link');

    return (
        <AppLayout
            title={t('settings.title')}
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: t('settings.title'), href: '/settings' },
                { label: t('settings.security.crumb') },
            ]}
        >
            <SettingsTabs current="/settings/security" />

            {flash.passwordChanged && <Alert severity="success">{t('settings.security.password.changed')}</Alert>}

            <SectionTitle note={t('settings.security.credentials.count', { count: credentials.length })}>
                {t('settings.security.credentials.heading')}
            </SectionTitle>
            {errors.credential && <Alert severity="error" sx={{ mb: 1 }}>{errors.credential}</Alert>}
            <CredentialList
                credentials={credentials}
                onRemove={confirmRemove}
                onRename={setRenaming}
            />

            <RenameCredentialDialog credential={renaming} onClose={() => setRenaming(null)} />
            {dialog}

            <SectionTitle>{t('settings.security.password.heading')}</SectionTitle>
            <PasswordSection hasPassword={hasPassword} />

            <SectionTitle>{t('settings.security.totp.heading')}</SectionTitle>
            <TotpSection hasTotp={hasTotp} pendingTotp={pendingTotp} />

            <SectionTitle>{t('settings.security.magic_link.heading')}</SectionTitle>
            <Paper variant="outlined">
                <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}>
                    <Box>
                        <Typography sx={{ fontSize: '0.9375rem' }}>{t('settings.security.magic_link.label')}</Typography>
                        <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                            {t('settings.security.magic_link.description')}
                        </Typography>
                    </Box>
                    <ToggleSwitch
                        checked={hasMagicLink}
                        label={t('settings.security.magic_link.heading')}
                        onChange={(next) => {
                            if (next) {
                                router.post('/security/magic-link');

                                return;
                            }

                            ask({
                                title: t('settings.security.magic_link.disable.title'),
                                description: t('settings.security.magic_link.disable.description'),
                                confirmText: t('settings.security.magic_link.disable.confirm'),
                                onConfirm: () => router.post('/security/magic-link/disable'),
                            });
                        }}
                    />
                </Box>
            </Paper>

            <SectionTitle note={t('common.count', { count: passkeys.length })}>
                {t('settings.security.passkey.heading')}
            </SectionTitle>

            {/* どの端末を登録したのかは、ここに出ていないと本人にも分からない */}
            <CredentialList credentials={passkeys} onRemove={confirmRemove} onRename={setRenaming} />

            <Paper variant="outlined" sx={{ p: 2, mt: 1.5 }}>
                {passkeyError && <Alert severity="error" sx={{ mb: 1.5 }}>{passkeyError}</Alert>}

                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('settings.security.passkey.description')}
                    </Typography>
                    <Button
                        variant="outlined"
                        color="inherit"
                        startIcon={<Icon name="fingerprint" />}
                        onClick={() => void addPasskey()}
                    >
                        {t('settings.security.passkey.register')}
                    </Button>
                </Stack>
            </Paper>

            <SectionTitle note={t('settings.security.recovery.remaining', { count: recoveryCodeCount })}>
                {t('settings.security.recovery.heading')}
            </SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                {flash.recoveryCodes && <RecoveryCodes codes={flash.recoveryCodes} />}

                {hasTotp && recoveryCodeCount === 0 && !flash.recoveryCodes && (
                    <Alert severity="warning" sx={{ mb: 1.5 }}>
                        {t('settings.security.recovery.exhausted')}
                    </Alert>
                )}

                <Button
                    variant="outlined"
                    color="inherit"
                    startIcon={<Icon name="rotate" />}
                    onClick={() =>
                        ask({
                            title: t('settings.security.recovery.regenerate.title'),
                            description: t('settings.security.recovery.regenerate.description'),
                            confirmText: t('settings.security.recovery.regenerate.confirm'),
                            onConfirm: () => router.post('/security/recovery-codes'),
                        })
                    }
                >
                    {t('settings.security.recovery.regenerate.confirm')}
                </Button>
            </Paper>
        </AppLayout>
    );
}
