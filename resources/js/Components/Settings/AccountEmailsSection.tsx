import { router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import ListRow from '../ListRow';
import OutlinedList from '../OutlinedList';
import RowAction from '../RowAction';
import { formatDateTime } from '../../lib/datetime';
import { useConfirm } from '../../lib/confirm';
import { t } from '../../lib/i18n';
import type { AccountEmail } from '../../types';

interface AccountEmailsSectionProps {
    emails: AccountEmail[];
}

/**
 * 主アドレスとは別に持つメールアドレスの一覧と追加。
 *
 * ここで足したアドレスは連携先へ渡す選択肢になるだけで、ログインには使えない。
 */
export default function AccountEmailsSection({ emails }: AccountEmailsSectionProps) {
    const { flash, errors } = usePage().props;
    const form = useForm({ email: '' });
    const { ask, dialog } = useConfirm();

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.post('/profile/emails', { onSuccess: () => form.reset() });
    };

    const remove = (row: AccountEmail): void => {
        ask({
            title: t('settings.profile.emails.remove_confirm.title', { email: row.email }),
            description: t('settings.profile.emails.remove_confirm.description'),
            confirmText: t('settings.profile.emails.remove'),
            onConfirm: () => router.post('/profile/emails/remove', { id: row.id }),
        });
    };

    const promote = (row: AccountEmail): void => {
        ask({
            title: t('settings.profile.emails.promote_confirm.title', { email: row.email }),
            description: t('settings.profile.emails.promote_confirm.description'),
            confirmText: t('settings.profile.emails.promote'),
            onConfirm: () => router.post('/profile/emails/primary', { id: row.id }),
        });
    };

    return (
        <Stack spacing={1.5}>
            {flash.accountEmail && (
                <Alert severity={flash.accountEmail === 'verify_failed' ? 'error' : 'success'}>
                    {t(`settings.profile.emails.flash.${flash.accountEmail}`)}
                </Alert>
            )}
            {typeof errors.id === 'string' && <Alert severity="error">{errors.id}</Alert>}

            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>{t('settings.profile.emails.lead')}</Typography>

            <OutlinedList empty={emails.length === 0 && t('settings.profile.emails.empty')}>
                {emails.map((row) => (
                    <ListRow key={row.id} actions={<EmailActions row={row} onRemove={remove} onPromote={promote} />}>
                        <Box sx={{ minWidth: 0 }}>
                            <Typography sx={{ fontSize: '0.9375rem', overflowWrap: 'anywhere' }}>{row.email}</Typography>
                            <EmailStatus row={row} />
                        </Box>
                    </ListRow>
                ))}
            </OutlinedList>

            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    <TextField
                        label={t('settings.profile.emails.new_label')}
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        error={Boolean(form.errors.email)}
                        helperText={form.errors.email}
                    />
                    <Button type="submit" variant="outlined" color="inherit" disabled={form.processing}>
                        {t('settings.profile.emails.submit')}
                    </Button>
                </Stack>
            </Box>

            {dialog}
        </Stack>
    );
}

function EmailStatus({ row }: { row: AccountEmail }) {
    if (row.verified) return <Chip size="small" color="success" label={t('settings.profile.email.verified')} sx={{ mt: 0.5 }} />;

    const until = row.pendingUntil === null ? null : formatDateTime(row.pendingUntil);

    return (
        <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
            {until !== null
                ? t('settings.profile.emails.pending_until', { date: String(until) })
                : t('settings.profile.emails.expired')}
        </Typography>
    );
}

interface EmailActionsProps {
    row: AccountEmail;
    onRemove: (row: AccountEmail) => void;
    onPromote: (row: AccountEmail) => void;
}

function EmailActions({ row, onRemove, onPromote }: EmailActionsProps) {
    return (
        <Stack direction="row" spacing={1}>
            {row.verified && <RowAction onClick={() => onPromote(row)}>{t('settings.profile.emails.promote')}</RowAction>}
            {!row.verified && (
                <RowAction onClick={() => router.post('/profile/emails/resend', { id: row.id })}>
                    {t('settings.profile.emails.resend')}
                </RowAction>
            )}
            <RowAction destructive onClick={() => onRemove(row)}>
                {t('settings.profile.emails.remove')}
            </RowAction>
        </Stack>
    );
}
