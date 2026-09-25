import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import ListRow from '../ListRow';
import OutlinedList from '../OutlinedList';
import RowAction from '../RowAction';
import ServiceIcon from '../ServiceIcon';
import { formatDateTime } from '../../lib/datetime';
import { t } from '../../lib/i18n';
import type { ConnectedService } from '../../types';

interface ServiceListProps {
    services: ConnectedService[];
    onRevoke: (service: ConnectedService) => void;
    /** 渡すアドレスを選ばせる。選べるアドレスが無ければ null */
    onEditEmail: ((service: ConnectedService) => void) | null;
}

/**
 * 連携中のサービスの一覧。
 */
export default function ServiceList({ services, onRevoke, onEditEmail }: ServiceListProps) {
    return (
        <OutlinedList empty={services.length === 0 && t('dashboard.services.empty')}>
            {services.map((service) => (
                <ListRow key={service.id} actions={<ServiceActions service={service} onRevoke={onRevoke} onEditEmail={onEditEmail} />}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, minWidth: 0 }}>
                        <ServiceIcon name={service.name} iconUrl={service.iconUrl} />
                        <Box sx={{ minWidth: 0 }}>
                            <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                {service.name}
                                {service.trust === 'official' && <Chip size="small" label={t('dashboard.services.official')} />}
                            </Typography>
                            <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                {service.serviceUserId ?? connectedLabel(service.connectedAt)}
                                {!service.hasActiveToken && ` ・ ${t('dashboard.services.inactive')}`}
                            </Typography>
                            {service.email !== null && (
                                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled', overflowWrap: 'anywhere' }}>
                                    {t('dashboard.services.email_assigned', { email: service.email })}
                                </Typography>
                            )}
                        </Box>
                    </Box>
                </ListRow>
            ))}
        </OutlinedList>
    );
}

/**
 * @param connectedAt 連携した日時
 * @returns 日時が読めればそれを含めた文言
 */
function connectedLabel(connectedAt: ConnectedService['connectedAt']): string {
    const time = formatDateTime(connectedAt);

    return time ? t('dashboard.services.connected_at', { time: String(time) }) : t('dashboard.services.connected');
}

interface ServiceActionsProps {
    service: ConnectedService;
    onRevoke: (service: ConnectedService) => void;
    onEditEmail: ((service: ConnectedService) => void) | null;
}

function ServiceActions({ service, onRevoke, onEditEmail }: ServiceActionsProps) {
    return (
        <Stack direction="row" spacing={1}>
            {/* サービス側が設定画面を指定していれば案内する */}
            {service.settingsUrl !== null && (
                <RowAction onClick={() => window.open(service.settingsUrl ?? '', '_blank', 'noopener')}>
                    {t('dashboard.service.settings')}
                </RowAction>
            )}
            {onEditEmail !== null && (
                <RowAction onClick={() => onEditEmail(service)}>{t('dashboard.services.email')}</RowAction>
            )}
            <RowAction onClick={() => router.get(`/services/${service.id}/split`)}>{t('dashboard.services.split')}</RowAction>
            <RowAction destructive onClick={() => onRevoke(service)}>
                {t('dashboard.services.revoke')}
            </RowAction>
        </Stack>
    );
}
