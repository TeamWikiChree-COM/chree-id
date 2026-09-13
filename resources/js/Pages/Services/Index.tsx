import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '../../Components/AppLayout';
import Icon from '../../Components/Icon';
import RowAction from '../../Components/RowAction';
import SectionTitle from '../../Components/SectionTitle';
import ServiceIcon from '../../Components/ServiceIcon';
import { t } from '../../lib/i18n';
import { trustLabel } from '../../lib/services';
import type { OwnedService } from '../../types';

interface IndexProps {
    services: OwnedService[];
}

/**
 * 第三者が自分のサービスを見る画面。
 *
 * **管理画面とは別物。** ここに出るのは自分が登録したものだけで、
 * 信頼状態は表示するだけ。動かせるのは運営だけなので、操作の導線を置かない。
 */
export default function Index({ services }: IndexProps) {
    const { issuedSecret, serviceSaved, reviewRequested } = usePage().props.flash;

    return (
        <AppLayout
            title={t('services.title')}
            lead={t('services.lead')}
            crumbs={[{ label: t('services.crumb') }]}
        >
            <Stack spacing={1.5}>
                {serviceSaved && <Alert severity="success">{t('services.saved')}</Alert>}
                {reviewRequested && <Alert severity="success">{t('services.review.requested')}</Alert>}
            </Stack>

            {issuedSecret && (
                <Alert severity="warning" sx={{ mt: 1.5 }}>
                    <Typography sx={{ fontSize: '0.875rem', mb: 0.5 }}>{t('services.secret.warning')}</Typography>
                    <Box component="pre" sx={{ m: 0, fontSize: '0.8125rem', whiteSpace: 'pre-wrap', wordBreak: 'break-all' }}>
                        {t('services.secret.client_id')}: {issuedSecret.clientId}
                        {'\n'}
                        {t('services.secret.heading')}: {issuedSecret.secret}
                    </Box>
                </Alert>
            )}

            <SectionTitle note={t('services.count', { count: services.length })}>
                {t('services.heading')}
            </SectionTitle>

            <Paper variant="outlined">
                {services.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('services.empty')}
                    </Typography>
                )}

                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {services.map((service) => (
                        <Box
                            key={service.id}
                            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}
                        >
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, minWidth: 0 }}>
                                <ServiceIcon name={service.name} iconUrl={service.iconUrl} />
                                <Box sx={{ minWidth: 0 }}>
                                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                                        <Typography sx={{ fontSize: '0.9375rem' }}>{service.name}</Typography>
                                        <Chip size="small" label={trustLabel(service.trust)} />
                                        {service.reviewRequestedAt !== null && (
                                            <Chip size="small" color="info" variant="outlined" label={t('admin.clients.pending')} />
                                        )}
                                    </Stack>
                                    <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled', wordBreak: 'break-all' }}>
                                        {service.id}
                                    </Typography>
                                </Box>
                            </Box>

                            <Stack direction="row" spacing={1} sx={{ flexShrink: 0 }}>
                                {service.trust === 'unapproved' && service.reviewRequestedAt === null && (
                                    <RowAction onClick={() => router.post(`/services/${service.id}/review`)}>
                                        {t('services.review.request')}
                                    </RowAction>
                                )}
                                <RowAction onClick={() => router.get(`/services/${service.id}/edit`)}>
                                    {t('services.edit')}
                                </RowAction>
                            </Stack>
                        </Box>
                    ))}
                </Stack>
            </Paper>

            <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>
                {t('services.review.note')}
            </Typography>

            <Box sx={{ mt: 2 }}>
                <Button
                    variant="contained"
                    startIcon={<Icon name="plus" />}
                    onClick={() => router.get('/services/register')}
                >
                    {t('services.register')}
                </Button>
            </Box>
        </AppLayout>
    );
}
