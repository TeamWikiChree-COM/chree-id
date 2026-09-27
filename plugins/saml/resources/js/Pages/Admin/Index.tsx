import { router } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '@/Components/AppLayout';
import Icon from '@/Components/Icon';
import RowDivider from '@/Components/RowDivider';
import SectionTitle from '@/Components/SectionTitle';
import { t as core } from '@/lib/i18n';
import KeySection, { type SigningKey } from '../../components/KeySection';
import { t } from '../../lib/i18n';

interface ServiceProvider {
    id: number;
    clientId: string;
    clientName: string;
    entityId: string;
    acsUrl: string;
    /** AuthnRequest の署名を確かめるか (SP の証明書を登録したか) */
    signedRequests: boolean;
}

interface IndexProps {
    /** 署名の鍵。まだ無ければ null */
    signingKey: SigningKey | null;
    /** 鍵を作った・取り込んだ直後だけ true */
    keyUpdated: boolean;
    metadataUrl: string;
    providers: ServiceProvider[];
}

/**
 * SAML IdP の管理画面。署名の鍵と、SAML でつなぐサービス。
 */
export default function Index({ signingKey, keyUpdated, metadataUrl, providers }: IndexProps) {
    return (
        <AppLayout
            title={t('admin.title')}
            lead={t('admin.lead')}
            crumbs={[{ label: core('admin.crumb'), href: '/admin' }, { label: t('admin.crumb') }]}
        >
            {keyUpdated && <Alert severity="success">{t('admin.key.updated')}</Alert>}

            <KeySection signingKey={signingKey} metadataUrl={metadataUrl} />

            <SectionTitle>{t('admin.providers.heading')}</SectionTitle>
            <Box>
                <Button variant="contained" startIcon={<Icon name="plus" />} onClick={() => router.get('/plugins/saml/admin/providers/create')}>
                    {t('admin.providers.add')}
                </Button>
            </Box>
            <Paper variant="outlined">
                <Stack divider={<RowDivider />}>
                    {providers.length === 0 && (
                        <Typography sx={{ px: 2, py: 1.5, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            {t('admin.providers.empty')}
                        </Typography>
                    )}

                    {providers.map((sp) => (
                        <Box key={sp.id} sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}>
                            <Box sx={{ minWidth: 0 }}>
                                <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                    {sp.clientName}
                                    {sp.signedRequests && <Chip size="small" label={t('admin.providers.signed')} />}
                                </Typography>
                                <Typography component="code" sx={{ display: 'block', fontSize: '0.8125rem', color: 'text.disabled', wordBreak: 'break-all' }}>
                                    {sp.entityId}
                                </Typography>
                                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled', wordBreak: 'break-all' }}>
                                    {sp.acsUrl}
                                </Typography>
                            </Box>
                            <Button
                                variant="outlined"
                                color="inherit"
                                size="small"
                                sx={{ flexShrink: 0 }}
                                onClick={() => router.get(`/plugins/saml/admin/providers/${sp.id}/edit`)}
                            >
                                {t('admin.providers.edit')}
                            </Button>
                        </Box>
                    ))}
                </Stack>
            </Paper>
        </AppLayout>
    );
}
