import { Deferred } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import AppLayout from '@/Components/AppLayout';
import ListRow from '@/Components/ListRow';
import OutlinedList from '@/Components/OutlinedList';
import RowAction from '@/Components/RowAction';
import SectionTitle from '@/Components/SectionTitle';
import ServiceIcon from '@/Components/ServiceIcon';
import ListSkeleton from '@/Components/Skeletons/ListSkeleton';
import { formatDateTime } from '@/lib/datetime';
import { t as core } from '@/lib/i18n';
import { t } from '../lib/i18n';

interface Wiki {
    name: string;
    url: string;
    /** ウィキのロゴ。無ければ null (頭文字で代わりを出す) */
    iconUrl: string | null;
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
    /** 各サービスへ問い合わせるので後から届く */
    groups?: WikiGroup[];
}

/**
 * 連携しているサービスのウィキを、サービスごとにまとめて出す。
 */
const Index = ({ groups }: IndexProps) => {
    return (
        <AppLayout title={t('page.title')} lead={t('page.lead')} crumbs={[{ label: core('dashboard.crumb'), href: '/' }, { label: t('page.title') }]}>
            <Deferred data="groups" fallback={<ListSkeleton count={3} />}>
                <WikiGroups groups={groups ?? []} />
            </Deferred>
        </AppLayout>
    );
};

/**
 * サービスごとのウィキ一覧。取れなかったサービスは警告だけ出す。
 *
 * @param groups サービスごとのまとまり
 */
const WikiGroups = ({ groups }: { groups: WikiGroup[] }) => {
    return (
        <>
            {groups.length === 0 && <Typography sx={{ color: 'text.secondary' }}>{t('page.no_sources')}</Typography>}

            {groups.map((group) => (
                <Box key={group.key}>
                    <SectionTitle note={group.failed ? undefined : core('common.count', { count: group.wikis.length })}>{group.label}</SectionTitle>
                    {group.failed
                        ? <Alert severity="warning">{t('page.failed')}</Alert>
                        : (
                            <OutlinedList empty={group.wikis.length === 0 && t('page.empty')}>
                                {group.wikis.map((wiki) => (
                                    <ListRow
                                        key={wiki.url}
                                        onClick={() => openInNewTab(wiki.url)}
                                        actions={wiki.settingsUrl === null ? undefined : <SettingsAction url={wiki.settingsUrl} />}
                                    >
                                        <WikiSummary wiki={wiki} />
                                    </ListRow>
                                ))}
                            </OutlinedList>
                        )}
                </Box>
            ))}
        </>
    );
};

const WikiSummary = ({ wiki }: { wiki: Wiki }) => {
    const updated = formatDateTime(wiki.updatedAt);
    const meta = [
        wiki.views !== null && t('page.views', { count: wiki.views.toLocaleString() }),
        updated !== null && t('page.updated_at', { date: String(updated) }),
    ].filter(Boolean).join(' ・ ');

    return (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, minWidth: 0 }}>
            <ServiceIcon name={wiki.name} iconUrl={wiki.iconUrl} />
            <Box sx={{ minWidth: 0 }}>
                <Typography sx={{ fontSize: '0.9375rem' }}>{wiki.name}</Typography>
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled', overflowWrap: 'anywhere' }}>{wiki.url}</Typography>
                {meta !== '' && <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>{meta}</Typography>}
            </Box>
        </Box>
    );
};

/**
 * @param url 開く先
 */
function openInNewTab(url: string): void {
    window.open(url, '_blank', 'noopener');
}

/**
 * 行そのものはウィキを開く。設定画面だけは行の右に置く (操作が1つだけなのでダイアログにしない)。
 */
const SettingsAction = ({ url }: { url: string }) => {
    return (
        // 行の onClick まで伝わると、設定と一緒にウィキも開いてしまう
        <Box onClick={(event) => event.stopPropagation()} sx={{ flexShrink: 0 }}>
            <RowAction onClick={() => openInNewTab(url)}>{t('action.settings')}</RowAction>
        </Box>
    );
};

export default Index;
