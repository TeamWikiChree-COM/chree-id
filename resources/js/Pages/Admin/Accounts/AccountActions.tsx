import { router, useForm } from '@inertiajs/react';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import { useState } from 'react';
import { useConfirm } from '../../../lib/confirm';
import { t } from '../../../lib/i18n';
import type { ConfirmRequest } from '../../../Components/ConfirmDialog';
import type { AdminAccount } from './types';

/** act() に渡す確認内容。実行そのものは act() が組み立てる */
type ConfirmText = Omit<ConfirmRequest, 'onConfirm'>;

interface AccountActionsProps {
    account: AdminAccount;
    /** 退会したアカウントが消えるまでの日数 */
    graceDays: number;
}

/**
 * アカウント詳細での操作。
 *
 * 一覧の行には置かない。数が多く、行の中で押し間違えると取り返しがつかないものを含むため。
 * **自分のアカウントには出さないこと。** 締め出されると管理画面に戻れなくなる。
 */
export default function AccountActions({ account, graceDays }: AccountActionsProps) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ display_name: account.displayName ?? '', email: account.email ?? '' });
    const { ask, dialog } = useConfirm();

    /**
     * 確認の文言を渡したものだけダイアログを挟む。停止の解除のように戻せる操作では聞かない。
     */
    const act = (action: string, confirmation?: ConfirmText): void => {
        const run = (): void => router.post(`/admin/accounts/${account.id}/act`, { action });
        if (confirmation === undefined) return run();

        ask({ ...confirmation, onConfirm: run });
    };

    const save = (): void => {
        form.post(`/admin/accounts/${account.id}`, { onSuccess: () => setEditing(false) });
    };

    const confirmOf = (key: 'promote' | 'suspend' | 'purge'): ConfirmText => ({
        title: t(`admin.accounts.row.${key}.title`),
        description: t(`admin.accounts.row.${key}.description`),
        confirmText: t(`admin.accounts.row.${key}.confirm`),
    });

    return (
        <Paper variant="outlined" sx={{ p: 2 }}>
            <Stack useFlexGap direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                <Button variant="outlined" color="inherit" onClick={() => setEditing(!editing)}>
                    {editing ? t('admin.accounts.row.action.close') : t('admin.accounts.row.action.edit')}
                </Button>
                {!account.isDeleted && account.origin === 'service' && (
                    <Button variant="outlined" color="inherit" onClick={() => act('promote', confirmOf('promote'))}>
                        {t('admin.accounts.row.action.promote')}
                    </Button>
                )}
                {account.isDeleted && (
                    <Button variant="outlined" color="inherit" onClick={() => act('restore')}>{t('admin.accounts.row.action.restore')}</Button>
                )}
                {!account.isDeleted && account.isSuspended && (
                    <Button variant="outlined" color="inherit" onClick={() => act('unsuspend')}>{t('admin.accounts.row.action.unsuspend')}</Button>
                )}
                {!account.isDeleted && !account.isSuspended && (
                    <Button variant="outlined" color="inherit" onClick={() => act('suspend', confirmOf('suspend'))}>
                        {t('admin.accounts.row.action.suspend')}
                    </Button>
                )}
                {!account.isDeleted && (
                    <Button
                        variant="outlined"
                        color="error"
                        onClick={() => act('withdraw', {
                            title: t('admin.accounts.row.withdraw.title'),
                            description: t('admin.accounts.row.withdraw.description', { days: graceDays }),
                            confirmText: t('admin.accounts.row.withdraw.confirm'),
                        })}
                    >
                        {t('admin.accounts.row.withdraw.confirm')}
                    </Button>
                )}
                <Button
                    variant="outlined"
                    color="error"
                    onClick={() => act('purge', { ...confirmOf('purge'), expected: account.email ?? account.id })}
                >
                    {t('admin.accounts.row.action.purge')}
                </Button>
            </Stack>

            {editing && (
                <Stack spacing={1.5} sx={{ mt: 2, alignItems: 'flex-start' }}>
                    <TextField
                        label={t('admin.accounts.fields.display_name')}
                        value={form.data.display_name}
                        onChange={(e) => form.setData('display_name', e.target.value)}
                        error={Boolean(form.errors.display_name)}
                        helperText={form.errors.display_name}
                    />
                    <TextField
                        label={t('admin.accounts.fields.email')}
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        error={Boolean(form.errors.email)}
                        helperText={form.errors.email ?? t('admin.accounts.row.email_helper')}
                    />
                    <Button variant="contained" onClick={save} disabled={form.processing}>
                        {t('admin.accounts.row.save')}
                    </Button>
                </Stack>
            )}

            {dialog}
        </Paper>
    );
}
