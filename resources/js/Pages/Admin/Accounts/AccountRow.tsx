import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import Icon from '../../../Components/Icon';
import { formatDateTime } from '../../../lib/datetime';
import { t } from '../../../lib/i18n';
import { CREDENTIAL_LABELS } from './credentialLabels';
import type { AdminAccount } from './types';

interface AccountRowProps {
    account: AdminAccount;
    /** 自分の行か */
    isSelf: boolean;
    /** 退会したアカウントが消えるまでの日数 */
    graceDays: number;
    /** 一覧では行そのものを詳細への入口にする。詳細ページでは押せない */
    linked?: boolean;
}

/**
 * アカウントの1行 (見るだけ)。操作は詳細ページの AccountActions にまとめる。
 */
export default function AccountRow({ account, isSelf, graceDays, linked = false }: AccountRowProps) {
    const go = (): void => router.get(`/admin/accounts/${account.id}`);

    return (
        <Box
            role={linked ? 'button' : undefined}
            tabIndex={linked ? 0 : undefined}
            onClick={linked ? go : undefined}
            onKeyDown={(event) => { if (linked && event.key === 'Enter') go(); }}
            sx={{
                px: 2,
                py: 1.5,
                opacity: account.isDeleted ? 0.6 : 1,
                ...(linked && { cursor: 'pointer', '&:hover': { bgcolor: 'action.hover' } }),
            }}
        >
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

                    {linked && <Icon name="chevron-right" sx={{ fontSize: '0.75rem', color: 'text.disabled' }} />}
                </Box>
            </Box>

        </Box>
    );
}
