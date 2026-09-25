import { useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import InertiaLink from '../../Components/InertiaLink';
import SectionTitle from '../../Components/SectionTitle';
import SettingsTabs from '../../Components/SettingsTabs';
import AccountEmailsSection from '../../Components/Settings/AccountEmailsSection';
import IconSection from '../../Components/Settings/IconSection';
import StickySaveBar from '../../Components/StickySaveBar';
import { t } from '../../lib/i18n';
import type { AccountEmail, IconSourceValue } from '../../types';

interface ProfileProps {
    /** 未設定なら null */
    displayName: string | null;
    email: string | null;
    emailVerified: boolean;
    iconSource: IconSourceValue;
    /** いま表示されている絵。未設定なら null */
    iconUrl: string | null;
    /** 確認待ちのメールアドレス変更。無ければ null */
    pendingEmail: { email: string; expiresAt: string } | null;
    /** 追加のアドレス。サービスアカウントは持たないので null */
    emails: AccountEmail[] | null;
}

export default function Profile({ displayName, email, emailVerified, iconSource, iconUrl, pendingEmail, emails }: ProfileProps) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, isDirty, reset } = useForm({ display_name: displayName ?? '' });

    const submit = (): void => {
        post('/profile');
    };

    return (
        <AppLayout
            title={t('settings.title')}
            crumbs={[{ label: t('settings.title') }]}
        >
            <SettingsTabs current="/settings" />

            <Stack spacing={1.5}>
                {flash.profileSaved && <Alert severity="success">{t('settings.profile.saved')}</Alert>}
                {flash.iconSaved && <Alert severity="success">{t('settings.profile.icon_saved')}</Alert>}
                {flash.verificationSent && (
                    <Alert severity="info">{t('settings.profile.verification_sent')}</Alert>
                )}
                {flash.emailVerified === true && <Alert severity="success">{t('settings.profile.email_verified')}</Alert>}
                {flash.emailVerified === false && (
                    <Alert severity="error">{t('settings.profile.email_verify_failed')}</Alert>
                )}
                {flash.emailChangeSent && (
                    <Alert severity="info">{t('settings.profile.email_change_sent')}</Alert>
                )}
                {flash.emailChangeCancelled && <Alert severity="success">{t('settings.profile.email_change_cancelled')}</Alert>}
                {flash.emailChanged === true && <Alert severity="success">{t('settings.profile.email_changed')}</Alert>}
                {flash.emailChanged === false && (
                    <Alert severity="error">{t('settings.profile.email_verify_failed')}</Alert>
                )}
            </Stack>

            <SectionTitle>{t('settings.profile.icon.heading')}</SectionTitle>
            <IconSection iconSource={iconSource} iconUrl={iconUrl} hasEmail={email !== null} />

            <SectionTitle>{t('settings.profile.display_name.heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Box
                    component="form"
                    onSubmit={(event: FormEvent<HTMLFormElement>) => {
                        event.preventDefault();
                        submit();
                    }}
                    noValidate
                >
                    <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                        <TextField
                            label={t('settings.profile.display_name.label')}
                            value={data.display_name}
                            onChange={(e) => setData('display_name', e.target.value)}
                            error={Boolean(errors.display_name)}
                            helperText={errors.display_name ?? t('settings.profile.display_name.hint')}
                            autoComplete="nickname"
                        />
                    </Stack>
                </Box>
            </Paper>

            <StickySaveBar open={isDirty} saving={processing} onSave={submit} onDiscard={() => reset()} />

            <SectionTitle>{t('settings.profile.email.heading')}</SectionTitle>
            <AccountEmailsSection email={email} emailVerified={emailVerified} pendingEmail={pendingEmail} emails={emails} />

            <SectionTitle>{t('settings.profile.withdraw.heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('settings.profile.withdraw.notice')}
                    </Typography>
                    <Button component={InertiaLink} href="/settings/withdraw" variant="outlined" color="error">
                        {t('settings.profile.withdraw.link')}
                    </Button>
                </Stack>
            </Paper>
        </AppLayout>
    );
}
