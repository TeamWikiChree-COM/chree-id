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
import type { ConfirmRequest } from '../../../Components/ConfirmDialog';
import type { AdminAccount } from './types';

/** 認証方式の表示ラベル */
const CREDENTIAL_LABELS: Record<string, string> = {
    password: 'パスワード',
    passkey: 'パスキー',
    totp: '2FA',
    magic_link: 'マジックリンク',
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
                            {account.displayName || '(未設定)'}
                        </Typography>
                        <Chip
                            size="small"
                            variant="outlined"
                            label={account.origin === 'service' ? 'サービスアカウント' : 'ユーザーアカウント'}
                        />
                        {isSelf && <Chip size="small" variant="outlined" label="自分" />}
                        {account.isAdmin && <Chip size="small" color="primary" label="管理者" />}
                        {account.isDeleted && <Chip size="small" color="error" label="退会済み" />}
                        {account.isSuspended && !account.isDeleted && <Chip size="small" color="error" label="停止中" />}
                        {account.email && (
                            <Chip
                                size="small"
                                color={account.isEmailVerified ? 'success' : 'default'}
                                label={account.isEmailVerified ? '確認済み' : '未確認'}
                            />
                        )}
                    </Box>

                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', mb: 0.25 }}>
                        {account.email || '(メールアドレス未登録)'}
                    </Typography>

                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, flexWrap: 'wrap' }}>
                        <Typography component="code" sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                            {account.id}
                        </Typography>

                        {account.credentialTypes.length > 0 && (
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                認証: {account.credentialTypes.map((t) => CREDENTIAL_LABELS[t] ?? t).join(', ')}
                            </Typography>
                        )}

                        {account.isDeleted && account.deletedAt !== null && (
                            <Typography sx={{ fontSize: '0.75rem', color: 'error.main' }}>
                                {account.deletedAt} に退会。{graceDays} 日後に削除されます
                            </Typography>
                        )}
                    </Box>
                </Box>

                <Box sx={{ textAlign: 'right', flexShrink: 0 }}>
                    <Typography sx={{ fontSize: '0.8125rem', color: 'text.secondary', mb: 0.5 }}>
                        {account.createdAt}
                    </Typography>

                    {!isSelf && (
                        <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                            <RowAction onClick={() => setEditing(!editing)}>{editing ? '閉じる' : '編集'}</RowAction>

                            {account.isDeleted && <RowAction onClick={() => act('restore')}>退会を取り消す</RowAction>}

                            {!account.isDeleted && account.isSuspended && (
                                <RowAction onClick={() => act('unsuspend')}>停止を解除</RowAction>
                            )}

                            {!account.isDeleted && !account.isSuspended && (
                                <RowAction
                                    onClick={() =>
                                        act('suspend', {
                                            title: 'このアカウントを停止しますか',
                                            description: 'ログインできなくなります。停止はあとから解除できます',
                                            confirmText: '停止する',
                                        })
                                    }
                                >
                                    停止
                                </RowAction>
                            )}

                            {!account.isDeleted && (
                                <RowAction
                                    destructive
                                    onClick={() =>
                                        act('withdraw', {
                                            title: 'このアカウントを退会させますか',
                                            description: `${String(graceDays)} 日以内なら取り消せます。過ぎると行ごと消えます`,
                                            confirmText: '退会させる',
                                        })
                                    }
                                >
                                    退会させる
                                </RowAction>
                            )}

                            <RowAction
                                destructive
                                onClick={() =>
                                    act('purge', {
                                        title: 'このアカウントを完全に削除しますか',
                                        description:
                                            '猶予を待たずに消します。連携先のサービスのアカウントも道連れになり、元に戻せません',
                                        confirmText: '完全に削除する',
                                        expected: account.email ?? account.id,
                                    })
                                }
                            >
                                完全に削除
                            </RowAction>
                        </Box>
                    )}
                </Box>
            </Box>

            {editing && (
                <Stack direction="row" spacing={1.5} sx={{ mt: 1.5, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                    <TextField
                        size="small"
                        label="表示名"
                        value={form.data.display_name}
                        onChange={(e) => form.setData('display_name', e.target.value)}
                        error={Boolean(form.errors.display_name)}
                        helperText={form.errors.display_name}
                    />
                    <TextField
                        size="small"
                        label="メールアドレス"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        error={Boolean(form.errors.email)}
                        helperText={form.errors.email ?? '変更すると確認は取り直しになります'}
                    />
                    <Button variant="contained" size="small" onClick={save} disabled={form.processing}>
                        保存
                    </Button>
                </Stack>
            )}

            {dialog}
        </Box>
    );
}
