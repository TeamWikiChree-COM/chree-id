import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import AppLayout from '@/Components/AppLayout';
import ListRow from '@/Components/ListRow';
import OutlinedList from '@/Components/OutlinedList';
import SectionTitle from '@/Components/SectionTitle';
import { useActions } from '@/lib/actions';
import { formatDateTime } from '@/lib/datetime';
import { t as core } from '@/lib/i18n';
import { t } from '../lib/i18n';

interface Wiki {
    name: string;
    url: string;
    /** サービス側の設定画面。無ければ null */
    settingsUrl: string | null;
    /** サービスが数えていなければ null */
    views: number | null;
    updatedAt: string | null;
}

interface WikiGroup {
    key: string;
    label: string;
    /** このサービスから取れなかった */
    failed: boolean;
    wikis: Wiki[];
}

interface IndexProps {
    groups: WikiGroup[];
}

/**
 * 連携しているサービスのウィキを、サービスごとにまとめて出す。
 */
export default function Index({ groups }: IndexProps) {
    const { open, dialog } = useActions();

    const openActions = (wiki: Wiki): void => {
        open({
            title: wiki.name,
            detail: wiki.url,
            actions: [
                { label: t('action.open'), onClick: () => window.open(wiki.url, '_blank', 'noopener') },
                ...(wiki.settingsUrl === null ? [] : [{
                    label: t('action.settings'),
                    description: t('action.settings_description'),
                    onClick: () => window.open(wiki.settingsUrl ?? '', '_blank', 'noopener'),
                }]),
            ],
        });
    };

    return (
        <AppLayout
            title={t('page.title')}
            lead={t('page.lead')}
            crumbs={[{ label: core('dashboard.crumb'), href: '/' }, { label: t('page.title') }]}
        >
            {groups.length === 0 && <Typography sx={{ color: 'text.secondary' }}>{t('page.no_sources')}</Typography>}

            {groups.map((group) => (
                <Box key={group.key}>
                    <SectionTitle note={core('common.count', { count: group.wikis.length })}>{group.label}</SectionTitle>
                    {group.failed
                        ? <Alert severity="warning">{t('page.failed')}</Alert>
                        : (
                            <OutlinedList empty={group.wikis.length === 0 && t('page.empty')}>
                                {group.wikis.map((wiki) => (
                                    <ListRow key={wiki.url} onClick={() => openActions(wiki)}>
                                        <WikiSummary wiki={wiki} />
                                    </ListRow>
                                ))}
                            </OutlinedList>
                        )}
                </Box>
            ))}

            {dialog}
        </AppLayout>
    );
}

function WikiSummary({ wiki }: { wiki: Wiki }) {
    const updated = formatDateTime(wiki.updatedAt);
    const meta = [
        wiki.views !== null && t('page.views', { count: wiki.views.toLocaleString() }),
        updated !== null && t('page.updated_at', { date: String(updated) }),
    ].filter(Boolean).join(' ・ ');

    return (
        <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontSize: '0.9375rem' }}>{wiki.name}</Typography>
            <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled', overflowWrap: 'anywhere' }}>{wiki.url}</Typography>
            {meta !== '' && <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>{meta}</Typography>}
        </Box>
    );
}
