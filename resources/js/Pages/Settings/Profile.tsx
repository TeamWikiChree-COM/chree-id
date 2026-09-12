import { router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import InertiaLink from '../../Components/InertiaLink';
import Icon from '../../Components/Icon';
import SectionTitle from '../../Components/SectionTitle';
import SettingsTabs from '../../Components/SettingsTabs';
import IconSection from '../../Components/Settings/IconSection';
import { formatDateTime } from '../../lib/datetime';
import { t } from '../../lib/i18n';
import type { IconSourceValue } from '../../types';

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
}

export default function Profile({ displayName, email, emailVerified, iconSource, iconUrl, pendingEmail }: ProfileProps) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ display_name: displayName ?? '' });
    // 現在のアドレスは上に出ている。ここに入れておくと、そのまま送信して
    // 「確認メールを送りました」が出るのに何も変わらない
    const emailForm = useForm({ email: '' });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/profile');
    };

    const submitEmail = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        emailForm.post('/profile/email/change', { onSuccess: () => emailForm.reset() });
    };

    return (
        <AppLayout
            title={t('settings.title')}
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: t('settings.title') }]}
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
                <Box component="form" onSubmit={submit} noValidate>
                    <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                        <TextField
                            label={t('settings.profile.display_name.label')}
                            value={data.display_name}
                            onChange={(e) => setData('display_name', e.target.value)}
                            error={Boolean(errors.display_name)}
                            helperText={errors.display_name ?? t('settings.profile.display_name.hint')}
                            autoComplete="nickname"
                        />
                        <Button type="submit" variant="contained" disabled={processing}>
                            {t('settings.common.save')}
                        </Button>
                    </Stack>
                </Box>
            </Paper>

            <SectionTitle>{t('settings.profile.email.heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <Typography sx={{ fontSize: '0.9375rem' }}>{email ?? t('settings.profile.email.unset')}</Typography>
                        <Chip
                            size="small"
                            label={emailVerified ? t('settings.profile.email.verified') : t('settings.profile.email.unverified')}
                            color={emailVerified ? 'success' : 'default'}
                        />
                    </Stack>

                    {email !== null && !emailVerified && (
                        <>
                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                {t('settings.profile.email.unverified_notice')}
                            </Typography>
                            <Button
                                variant="outlined"
                                color="inherit"
                                startIcon={<Icon name="envelope" />}
                                onClick={() => router.post('/profile/email/verify')}
                            >
                                {t('settings.profile.email.send_verification')}
                            </Button>
                        </>
                    )}

                    {pendingEmail !== null && (
                        <Alert
                            severity="info"
                            sx={{ width: '100%' }}
                            action={
                                <Button
                                    size="small"
                                    color="inherit"
                                    onClick={() => router.post('/profile/email/change/cancel')}
                                >
                                    {t('settings.profile.email.pending_cancel')}
                                </Button>
                            }
                        >
                            {t('settings.profile.email.pending_notice', { email: pendingEmail.email })}
                            {formatDateTime(pendingEmail.expiresAt) !== null &&
                                t('settings.profile.email.pending_until', { date: String(formatDateTime(pendingEmail.expiresAt)) })}
                        </Alert>
                    )}

                    <Box component="form" onSubmit={submitEmail} noValidate sx={{ width: '100%', pt: 1 }}>
                        <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                            <TextField
                                label={t('settings.profile.email.new_label')}
                                placeholder={t('settings.profile.email.new_placeholder')}
                                type="email"
                                value={emailForm.data.email}
                                onChange={(e) => emailForm.setData('email', e.target.value)}
                                error={Boolean(emailForm.errors.email)}
                                helperText={
                                    emailForm.errors.email ??
                                    t('settings.profile.email.new_hint')
                                }
                            />
                            <Button type="submit" variant="outlined" color="inherit" disabled={emailForm.processing}>
                                {t('settings.profile.email.submit')}
                            </Button>
                        </Stack>
                    </Box>
                </Stack>
            </Paper>

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
