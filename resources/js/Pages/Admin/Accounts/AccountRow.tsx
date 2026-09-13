import { router, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import Icon from '../../../Components/Icon';
import RowAction from '../../../Components/RowAction';
import { useConfirm } from '../../../lib/confirm';
import { formatDateTime } from '../../../lib/datetime';
import { t } from '../../../lib/i18n';
import type { ConfirmRequest } from '../../../Components/ConfirmDialog';
import type { AdminAccount } from './types';

/** 認証方式の表示ラベル */
const CREDENTIAL_LABELS: Record<string, string> = {
    password: t('admin.accounts.row.credential.password'),
    passkey: t('admin.accounts.row.credential.passkey'),
    totp: t('admin.accounts.row.credential.totp'),
    magic_link: t('admin.accounts.row.credential.magic_link'),
};

/** act() に渡す確認内容。実行そのものは act() が組み立てる */
type ConfirmText = Omit<ConfirmRequest, 'onConfirm'>;

interface AccountRowProps {
    account: AdminAccount;
    /** 自分の行か。自分は操作させない */
    isSelf: boolean;
    /** 退会したアカウントが消えるまでの日数 */
    graceDays: number;
}

/**
 * アカウント一覧の1行。
 *
 * **自分の行では操作を出さない。** 締め出されると管理画面に戻れなくなる。
 * 自分が退会したいなら /settings/withdraw から。
 */
export default function AccountRow({ account, isSelf, graceDays }: AccountRowProps) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        display_name: account.displayName ?? '',
        email: account.email ?? '',
    });

    const { ask, dialog } = useConfirm();

    /**
     * 確認の文言を渡したものだけダイアログを挟む。
     * 停止の解除のように戻せる操作では聞かない。
     */
    const act = (action: string, confirmation?: ConfirmText): void => {
        if (confirmation === undefined) {
            router.post(`/admin/accounts/${account.id}/act`, { action });

            return;
        }

        ask({
            ...confirmation,
            onConfirm: () => router.post(`/admin/accounts/${account.id}/act`, { action }),
        });
    };

    const save = (): void => {
        form.post(`/admin/accounts/${account.id}`, { onSuccess: () => setEditing(false) });
    };

    return (
        <Box sx={{ px: 2, py: 1.5, opacity: account.isDeleted ? 0.6 : 1 }}>
            <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 2 }}>
                <Box sx={{ minWidth: 0 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexWrap: 'wrap', mb: 0.5 }}>
                        <Icon
                            name={account.origin === 'service' ? 'robot' : 'user'}
                            sx={{ color: 'text.secondary', fontSize: '0.9375rem' }}
                        />
                        <Typography sx={{ fontWeight: 600, fontSize: '0.9375rem' }}>
                            {account.displayName || t('admin.accounts.row.unset')}
                        </Typography>
                        <Chip
                            size="small"
                            variant="outlined"
                            label={account.origin === 'service' ? t('admin.accounts.row.origin.service') : t('admin.accounts.row.origin.user')}
                        />
                        {isSelf && <Chip size="small" variant="outlined" label={t('admin.accounts.row.self')} />}
                        {account.isAdmin && <Chip size="small" color="primary" label={t('admin.accounts.row.admin')} />}
                        {account.isDeleted && <Chip size="small" color="error" label={t('admin.accounts.row.withdrawn')} />}
                        {account.isSuspended && !account.isDeleted && <Chip size="small" color="error" label={t('admin.accounts.row.suspended')} />}
                        {account.email && (
                            <Chip
                                size="small"
                                color={account.isEmailVerified ? 'success' : 'default'}
                                label={account.isEmailVerified ? t('admin.accounts.row.email.verified') : t('admin.accounts.row.email.unverified')}
                            />
                        )}
                    </Box>

                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', mb: 0.25 }}>
                        {account.email || t('admin.accounts.row.no_email')}
                    </Typography>

                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, flexWrap: 'wrap' }}>
                        <Typography component="code" sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                            {account.id}
                        </Typography>

                        {account.credentialTypes.length > 0 && (
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                {t('admin.accounts.row.credentials', {
                                    list: account.credentialTypes.map((c) => CREDENTIAL_LABELS[c] ?? c).join(', '),
                                })}
                            </Typography>
                        )}

                        {account.services.map((service) => (
                            <Chip
                                key={`${service.clientId}:${service.serviceUserId ?? ''}`}
                                size="small"
                                variant="outlined"
                                icon={<Icon name="plug" />}
                                label={
                                    service.serviceUserId === null
                                        ? service.name
                                        : t('admin.accounts.row.service', {
                                              name: service.name,
                                              id: service.serviceUserId,
                                          })
                                }
                                sx={{ fontSize: '0.75rem' }}
                            />
                        ))}

                        {account.isDeleted && account.deletedAt !== null && (
                            <Typography sx={{ fontSize: '0.75rem', color: 'error.main' }}>
                                {t('admin.accounts.row.deleted_note', { date: formatDateTime(account.deletedAt) ?? account.deletedAt, days: graceDays })}
                            </Typography>
                        )}
                    </Box>
                </Box>

                <Box sx={{ textAlign: 'right', flexShrink: 0 }}>
                    <Typography sx={{ fontSize: '0.8125rem', color: 'text.secondary', mb: 0.5 }}>
                        {formatDateTime(account.createdAt)}
                    </Typography>

                    {!isSelf && (
                        <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                            <RowAction onClick={() => setEditing(!editing)}>{editing ? t('admin.accounts.row.action.close') : t('admin.accounts.row.action.edit')}</RowAction>

                            {account.isDeleted && <RowAction onClick={() => act('restore')}>{t('admin.accounts.row.action.restore')}</RowAction>}

                            {!account.isDeleted && account.isSuspended && (
                                <RowAction onClick={() => act('unsuspend')}>{t('admin.accounts.row.action.unsuspend')}</RowAction>
                            )}

                            {!account.isDeleted && !account.isSuspended && (
                                <RowAction
                                    onClick={() =>
                                        act('suspend', {
                                            title: t('admin.accounts.row.suspend.title'),
                                            description: t('admin.accounts.row.suspend.description'),
                                            confirmText: t('admin.accounts.row.suspend.confirm'),
                                        })
                                    }
                                >
                                    {t('admin.accounts.row.action.suspend')}
                                </RowAction>
                            )}

                            {!account.isDeleted && (
                                <RowAction
                                    destructive
                                    onClick={() =>
                                        act('withdraw', {
                                            title: t('admin.accounts.row.withdraw.title'),
                                            description: t('admin.accounts.row.withdraw.description', { days: graceDays }),
                                            confirmText: t('admin.accounts.row.withdraw.confirm'),
                                        })
                                    }
                                >
                                    {t('admin.accounts.row.withdraw.confirm')}
                                </RowAction>
                            )}

                            <RowAction
                                destructive
                                onClick={() =>
                                    act('purge', {
                                        title: t('admin.accounts.row.purge.title'),
                                        description: t('admin.accounts.row.purge.description'),
                                        confirmText: t('admin.accounts.row.purge.confirm'),
                                        expected: account.email ?? account.id,
                                    })
                                }
                            >
                                {t('admin.accounts.row.action.purge')}
                            </RowAction>
                        </Box>
                    )}
                </Box>
            </Box>

            {editing && (
                <Stack direction="row" spacing={1.5} sx={{ mt: 1.5, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                    <TextField
                        size="small"
                        label={t('admin.accounts.fields.display_name')}
                        value={form.data.display_name}
                        onChange={(e) => form.setData('display_name', e.target.value)}
                        error={Boolean(form.errors.display_name)}
                        helperText={form.errors.display_name}
                    />
                    <TextField
                        size="small"
                        label={t('admin.accounts.fields.email')}
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        error={Boolean(form.errors.email)}
                        helperText={form.errors.email ?? t('admin.accounts.row.email_helper')}
                    />
                    <Button variant="contained" size="small" onClick={save} disabled={form.processing}>
                        {t('admin.accounts.row.save')}
                    </Button>
                </Stack>
            )}

            {dialog}
        </Box>
    );
}
