import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import ListRow from '../ListRow';
import OutlinedList from '../OutlinedList';
import ServiceIcon from '../ServiceIcon';
import { formatDateTime } from '../../lib/datetime';
import { t } from '../../lib/i18n';
import type { ConnectedService } from '../../types';

interface ServiceListProps {
    services: ConnectedService[];
}

/**
 * 連携中のサービスの一覧。操作は行を押した先のページにまとめる。
 */
export default function ServiceList({ services }: ServiceListProps) {
    return (
        <OutlinedList empty={services.length === 0 && t('dashboard.services.empty')}>
            {services.map((service) => (
                <ListRow key={service.id} onClick={() => router.get(`/connected/${service.id}`)}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, minWidth: 0, flex: 1 }}>
                        <ServiceIcon name={service.name} iconUrl={service.iconUrl} />
                        <Box sx={{ minWidth: 0 }}>
                            <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                {service.name}
                                {service.trust === 'official' && (
                                    <Tooltip title={t('common.tooltip.official')}>
                                        <Chip size="small" label={t('dashboard.services.official')} />
                                    </Tooltip>
                                )}
                            </Typography>
                            <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                {service.serviceUserId ?? connectedLabel(service.connectedAt)}
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
