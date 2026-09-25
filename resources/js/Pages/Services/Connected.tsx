import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '../../Components/AppLayout';
import SectionTitle from '../../Components/SectionTitle';
import ServiceIcon from '../../Components/ServiceIcon';
import ServiceEmailForm from '../../Components/Services/ServiceEmailForm';
import { useConfirm } from '../../lib/confirm';
import { formatDateTime } from '../../lib/datetime';
import { t } from '../../lib/i18n';
import type { ConnectedService, EmailOption } from '../../types';

interface ConnectedProps {
    service: ConnectedService;
    /** 割り当てが無いときに渡っているアドレス */
    primaryEmail: string | null;
    /** 選べるアドレス。空ならこのアカウントでは選ばせない */
    emailOptions: EmailOption[];
}

/**
 * 連携しているサービス1件。操作は一覧の行ではなくここに置く。
 */
export default function Connected({ service, primaryEmail, emailOptions }: ConnectedProps) {
    const { flash } = usePage().props;
    const { ask, dialog } = useConfirm();
    const connectedAt = formatDateTime(service.connectedAt);

    const revoke = (): void => {
        ask({
            title: t('dashboard.services.revoke_confirm.title', { name: service.name }),
            description: t('dashboard.services.revoke_confirm.description'),
            confirmText: t('dashboard.services.revoke_confirm.confirm'),
            onConfirm: () => router.post(`/services/${service.clientId}/revoke`),
        });
    };

    return (
        <AppLayout
            title={service.name}
            crumbs={[{ label: t('dashboard.crumb'), href: '/' }, { label: service.name }]}
        >
            {flash.serviceEmailSaved && <Alert severity="success">{t('dashboard.service_email_saved')}</Alert>}

            <Paper variant="outlined" sx={{ p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, minWidth: 0 }}>
                    <ServiceIcon name={service.name} iconUrl={service.iconUrl} />
                    <Box sx={{ minWidth: 0 }}>
                        <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                            {service.name}
                            {service.trust === 'official' && <Chip size="small" label={t('dashboard.services.official')} />}
                        </Typography>
                        <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled', overflowWrap: 'anywhere' }}>
                            {[
                                service.serviceUserId,
                                connectedAt !== null && t('dashboard.services.connected_at', { time: String(connectedAt) }),
                                !service.hasActiveToken && t('dashboard.services.inactive'),
                            ].filter(Boolean).join(' ・ ')}
                        </Typography>
                    </Box>
                </Box>
            </Paper>

            <SectionTitle>{t('dashboard.services.email')}</SectionTitle>
            {emailOptions.length > 0
                ? <ServiceEmailForm service={service} options={emailOptions} primaryEmail={primaryEmail} />
                : (
                    <Paper variant="outlined" sx={{ p: 2 }}>
                        <Typography sx={{ fontSize: '0.9375rem', overflowWrap: 'anywhere' }}>
                            {service.email ?? primaryEmail ?? t('settings.profile.email.unset')}
                        </Typography>
                    </Paper>
                )}

            <SectionTitle>{t('admin.accounts.detail.actions')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack useFlexGap direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                    {service.settingsUrl !== null && (
                        <Button variant="outlined" color="inherit" onClick={() => window.open(service.settingsUrl ?? '', '_blank', 'noopener')}>
                            {t('dashboard.service.settings')}
                        </Button>
                    )}
                    <Button variant="outlined" color="inherit" onClick={() => router.get(`/services/${service.id}/split`)}>
                        {t('dashboard.services.split')}
                    </Button>
                    <Button variant="outlined" color="error" onClick={revoke}>
                        {t('dashboard.services.revoke')}
                    </Button>
                </Stack>
            </Paper>

            {dialog}
        </AppLayout>
    );
}
