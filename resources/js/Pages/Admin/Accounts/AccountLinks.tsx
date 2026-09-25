import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import CredentialPicker from '../../../Components/CredentialPicker';
import ListRow from '../../../Components/ListRow';
import OutlinedList from '../../../Components/OutlinedList';
import { useActions } from '../../../lib/actions';
import { useConfirm } from '../../../lib/confirm';
import { formatDateTime } from '../../../lib/datetime';
import { t } from '../../../lib/i18n';
import type { CredentialSummary } from '../../../types';
import { CREDENTIAL_LABELS } from './credentialLabels';
import type { AdminServiceLink } from './types';

interface AccountLinksProps {
    accountId: string;
    links: AdminServiceLink[];
    credentials: CredentialSummary[];
    /** 分離のときに持っていける認証手段のID */
    splittable: string[];
    /** 自分のアカウントは操作させない */
    readOnly: boolean;
}

/**
 * サービスアカウントの一覧。誤った紐付けを分離・削除できるようにする。
 *
 * **分離は sub を保ち、削除は sub ごと消す。** 迷ったら分離を選ぶ。
 */
export default function AccountLinks({ accountId, links, credentials, splittable, readOnly }: AccountLinksProps) {
    const { ask, dialog } = useConfirm();
    const actions = useActions();
    const [splitting, setSplitting] = useState<AdminServiceLink | null>(null);
    const [chosen, setChosen] = useState<string[]>([]);

    const options = credentials.filter((credential) => splittable.includes(credential.id));

    const openSplit = (link: AdminServiceLink): void => {
        setChosen(options.map((option) => option.id));
        setSplitting(link);
    };

    const split = (): void => {
        if (splitting === null) return;
        router.post(`/admin/accounts/${accountId}/links/${splitting.id}/split`, { credentials: chosen }, { onFinish: () => setSplitting(null) });
    };

    const unlink = (link: AdminServiceLink): void => {
        ask({
            title: t('admin.accounts.detail.unlink.title'),
            description: t('admin.accounts.detail.unlink.description', { name: link.name }),
            confirmText: t('admin.accounts.detail.unlink.confirm'),
            expected: link.serviceUserId ?? link.id,
            onConfirm: () => router.post(`/admin/accounts/${accountId}/links/${link.id}/delete`),
        });
    };

    const openActions = (link: AdminServiceLink): void => {
        actions.open({
            title: link.name,
            detail: link.serviceUserId === null ? undefined : link.serviceUserId,
            actions: [
                { label: t('admin.accounts.detail.split.action'), onClick: () => openSplit(link) },
                { label: t('admin.accounts.detail.unlink.confirm'), destructive: true, onClick: () => unlink(link) },
            ],
        });
    };

    return (
        <>
            <OutlinedList empty={links.length === 0 && t('admin.accounts.detail.links_empty')}>
                {links.map((link) => (
                    <ListRow key={link.id} onClick={readOnly ? undefined : () => openActions(link)}>
                        <Box sx={{ minWidth: 0 }}>
                            <Typography sx={{ fontSize: '0.9375rem' }}>
                                {link.serviceUserId === null ? link.name : t('admin.accounts.row.service', { name: link.name, id: link.serviceUserId })}
                            </Typography>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.disabled', wordBreak: 'break-all' }}>
                                {[
                                    t('admin.accounts.detail.sub', { sub: link.sub ?? '-' }),
                                    t('admin.accounts.detail.created_at', { date: formatDateTime(link.createdAt) ?? '-' }),
                                    link.claimedAt && t('admin.accounts.detail.claimed_at', { date: formatDateTime(link.claimedAt) ?? '-' }),
                                ].filter(Boolean).join(' / ')}
                            </Typography>
                        </Box>
                    </ListRow>
                ))}
            </OutlinedList>

            <Dialog open={splitting !== null} onClose={() => setSplitting(null)} maxWidth="sm" fullWidth>
                <DialogTitle>{t('admin.accounts.detail.split.title', { name: splitting?.name ?? '' })}</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                        {t('admin.accounts.detail.split.description')}
                    </Typography>
                    <CredentialPicker
                        options={options}
                        selected={chosen}
                        onChange={setChosen}
                        instruction={t('admin.accounts.detail.split.instruction')}
                        note={t('admin.accounts.detail.split.note')}
                        labels={CREDENTIAL_LABELS}
                    />
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setSplitting(null)}>{t('admin.accounts.detail.split.cancel')}</Button>
                    <Button variant="contained" onClick={split} disabled={chosen.length === 0}>
                        {t('admin.accounts.detail.split.confirm')}
                    </Button>
                </DialogActions>
            </Dialog>
            {actions.dialog}
            {dialog}
        </>
    );
}
