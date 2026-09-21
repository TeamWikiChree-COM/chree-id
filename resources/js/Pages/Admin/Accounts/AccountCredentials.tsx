import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import ListRow from '../../../Components/ListRow';
import OutlinedList from '../../../Components/OutlinedList';
import RowAction from '../../../Components/RowAction';
import { useConfirm } from '../../../lib/confirm';
import { formatDateTime } from '../../../lib/datetime';
import { t } from '../../../lib/i18n';
import type { CredentialSummary } from '../../../types';
import { CREDENTIAL_LABELS } from './credentialLabels';

interface AccountCredentialsProps {
    accountId: string;
    credentials: CredentialSummary[];
    /** 自分のアカウントは操作させない */
    readOnly: boolean;
}

/**
 * 認証手段の一覧。乗っ取りの疑いがあるときなどに、運営が1件ずつ外せるようにする。
 * 最後の1件はサーバ側で断られる (本人が入れなくなるため)。
 */
export default function AccountCredentials({ accountId, credentials, readOnly }: AccountCredentialsProps) {
    const { ask, dialog } = useConfirm();

    const remove = (credential: CredentialSummary): void => {
        ask({
            title: t('admin.accounts.detail.credential_remove.title'),
            description: t('admin.accounts.detail.credential_remove.description'),
            confirmText: t('admin.accounts.detail.credential_remove.confirm'),
            onConfirm: () => router.post(`/admin/accounts/${accountId}/credentials/${credential.id}/delete`),
        });
    };

    return (
        <>
            <OutlinedList empty={credentials.length === 0 && t('admin.accounts.detail.credentials_empty')}>
                {credentials.map((credential) => (
                    <ListRow
                        key={credential.id}
                        actions={!readOnly && (
                            <RowAction destructive onClick={() => remove(credential)}>
                                {t('admin.accounts.detail.credential_remove.confirm')}
                            </RowAction>
                        )}
                    >
                        <Box sx={{ minWidth: 0 }}>
                            <Typography sx={{ fontSize: '0.9375rem' }}>
                                {CREDENTIAL_LABELS[credential.type] ?? credential.type}
                                {credential.provider && ` (${credential.provider})`}
                                {credential.label && ` - ${credential.label}`}
                            </Typography>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                {[
                                    credential.detail,
                                    t('admin.accounts.detail.created_at', { date: formatDateTime(credential.createdAt) ?? '-' }),
                                    t('admin.accounts.detail.last_used_at', { date: formatDateTime(credential.lastUsedAt) ?? '-' }),
                                ].filter(Boolean).join(' / ')}
                            </Typography>
                        </Box>
                    </ListRow>
                ))}
            </OutlinedList>
            {dialog}
        </>
    );
}
