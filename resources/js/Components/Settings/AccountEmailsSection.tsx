import { router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import Tooltip from '@mui/material/Tooltip';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import LinkedText from '../LinkedText';
import ListRow from '../ListRow';
import OutlinedList from '../OutlinedList';
import type { RowActionItem } from '../ActionDialog';
import { formatDateTime } from '../../lib/datetime';
import { useActions } from '../../lib/actions';
import { useConfirm } from '../../lib/confirm';
import { t } from '../../lib/i18n';
import type { AccountEmail } from '../../types';

interface AccountEmailsSectionProps {
    /** 主アドレス。未設定なら null */
    email: string | null;
    emailVerified: boolean;
    /** 確認待ちのメールアドレス変更。無ければ null */
    pendingEmail: { email: string; expiresAt: string } | null;
    /** 追加のアドレス。サービスアカウントは持たないので null */
    emails: AccountEmail[] | null;
}

/**
 * メールアドレスの一覧 (主アドレスが先頭、続けて追加のアドレス)。
 *
 * ユーザーアカウントでは、主アドレスの変更も「追加して確認 → 主アドレスにする」で行う。
 * 追加のアドレスを持てないサービスアカウントにだけ、直接の変更フォームを出す。
 */
export default function AccountEmailsSection({ email, emailVerified, pendingEmail, emails }: AccountEmailsSectionProps) {
    const { flash, errors } = usePage().props;
    const { ask, dialog } = useConfirm();
    const actions = useActions();

    const extraActions = (row: AccountEmail): RowActionItem[] => [
        row.verified
            ? {
                  label: t('settings.profile.emails.promote'),
                  onClick: () => ask({
                      title: t('settings.profile.emails.promote_confirm.title', { email: row.email }),
                      description: t('settings.profile.emails.promote_confirm.description'),
                      confirmText: t('settings.profile.emails.promote'),
                      destructive: false,
                      onConfirm: () => router.post('/profile/emails/primary', { id: row.id }),
                  }),
              }
            : { label: t('settings.profile.emails.resend'), onClick: () => router.post('/profile/emails/resend', { id: row.id }) },
        {
            label: t('settings.profile.emails.remove'),
            destructive: true,
            onClick: () => ask({
                title: t('settings.profile.emails.remove_confirm.title', { email: row.email }),
                description: t('settings.profile.emails.remove_confirm.description'),
                confirmText: t('settings.profile.emails.remove'),
                onConfirm: () => router.post('/profile/emails/remove', { id: row.id }),
            }),
        },
    ];

    // 主アドレスでできるのは確認メールの送り直しだけ。確認済みなら押せる行にしない
    const openPrimary = email === null || emailVerified
        ? undefined
        : () => actions.open({
              title: email,
              actions: [{ label: t('settings.profile.email.send_verification'), onClick: () => router.post('/profile/email/verify') }],
          });

    return (
        <Stack spacing={1.5}>
            {flash.accountEmail && (
                <Alert severity={flash.accountEmail === 'verify_failed' ? 'error' : 'success'}>
                    {t(`settings.profile.emails.flash.${flash.accountEmail}`)}
                </Alert>
            )}
            {typeof errors.id === 'string' && <Alert severity="error">{errors.id}</Alert>}
            {pendingEmail !== null && <PendingChange pending={pendingEmail} />}

            <OutlinedList>
                <ListRow onClick={openPrimary}>
                    <Box sx={{ minWidth: 0 }}>
                        <Typography sx={{ fontSize: '0.9375rem', overflowWrap: 'anywhere' }}>{email ?? t('settings.profile.email.unset')}</Typography>
                        <Stack direction="row" spacing={0.5} sx={{ mt: 0.5 }}>
                            <Tooltip title={t('settings.profile.emails.primary_tooltip')}>
                                <Chip size="small" variant="outlined" label={t('settings.profile.emails.primary_badge')} />
                            </Tooltip>
                            {email !== null && <VerifiedChip verified={emailVerified} />}
                        </Stack>
                    </Box>
                </ListRow>
                {(emails ?? []).map((row) => (
                    <ListRow key={row.id} onClick={() => actions.open({ title: row.email, actions: extraActions(row) })}>
                        <Box sx={{ minWidth: 0 }}>
                            <Typography sx={{ fontSize: '0.9375rem', overflowWrap: 'anywhere' }}>{row.email}</Typography>
                            <ExtraStatus row={row} />
                        </Box>
                    </ListRow>
                ))}
            </OutlinedList>

            {emails !== null && (
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.secondary' }}><LinkedText text={t('settings.profile.emails.lead')} /></Typography>
            )}
            {emails !== null ? <AddForm /> : <ChangeForm />}

            {actions.dialog}
            {dialog}
        </Stack>
    );
}

function VerifiedChip({ verified }: { verified: boolean }) {
    return (
        <Tooltip title={verified ? t('settings.profile.email.verified_tooltip') : t('settings.profile.email.unverified_tooltip')}>
            <Chip
                size="small"
                color={verified ? 'success' : 'default'}
                label={verified ? t('settings.profile.email.verified') : t('settings.profile.email.unverified')}
            />
        </Tooltip>
    );
}

function ExtraStatus({ row }: { row: AccountEmail }) {
    if (row.verified) return <Box sx={{ mt: 0.5 }}><VerifiedChip verified /></Box>;

    const until = row.pendingUntil === null ? null : formatDateTime(row.pendingUntil);

    return (
        <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
            {until !== null ? t('settings.profile.emails.pending_until', { date: String(until) }) : t('settings.profile.emails.expired')}
        </Typography>
    );
}

function PendingChange({ pending }: { pending: { email: string; expiresAt: string } }) {
    const until = formatDateTime(pending.expiresAt);

    return (
        <Alert
            severity="info"
            action={<Button size="small" color="inherit" onClick={() => router.post('/profile/email/change/cancel')}>{t('settings.profile.email.pending_cancel')}</Button>}
        >
            {t('settings.profile.email.pending_notice', { email: pending.email })}
            {until !== null && t('settings.profile.email.pending_until', { date: String(until) })}
        </Alert>
    );
}

function AddForm() {
    const form = useForm({ email: '' });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.post('/profile/emails', { onSuccess: () => form.reset() });
    };

    return (
        <Box component="form" onSubmit={submit} noValidate>
            <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                <TextField
                    label={t('settings.profile.emails.new_label')}
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={Boolean(form.errors.email)}
                    helperText={form.errors.email ?? t('settings.profile.emails.new_hint')}
                />
                <Button type="submit" variant="outlined" color="inherit" disabled={form.processing}>
                    {t('settings.profile.emails.submit')}
                </Button>
            </Stack>
        </Box>
    );
}

function ChangeForm() {
    // 現在のアドレスは上に出ている。ここに入れておくと、そのまま送信して
    // 「確認メールを送りました」が出るのに何も変わらない
    const form = useForm({ email: '' });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.post('/profile/email/change', { onSuccess: () => form.reset() });
    };

    return (
        <Box component="form" onSubmit={submit} noValidate>
            <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                <TextField
                    label={t('settings.profile.email.new_label')}
                    placeholder={t('settings.profile.email.new_placeholder')}
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={Boolean(form.errors.email)}
                    helperText={form.errors.email ?? t('settings.profile.email.new_hint')}
                />
                <Button type="submit" variant="outlined" color="inherit" disabled={form.processing}>
                    {t('settings.profile.email.submit')}
                </Button>
            </Stack>
        </Box>
    );
}
