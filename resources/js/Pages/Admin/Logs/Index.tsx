import { Deferred, router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import AppLayout from '../../../Components/AppLayout';
import LogEntryRow from '../../../Components/LogEntryRow';
import SectionTitle from '../../../Components/SectionTitle';
import LogListSkeleton from '../../../Components/Skeletons/LogListSkeleton';
import { useConfirm } from '../../../lib/confirm';
import { t } from '../../../lib/i18n';
import type { LogEntry } from '../../../types';
import RowDivider from '../../../Components/RowDivider';

interface IndexProps {
    /** 読めるログファイル。新しい順 */
    files: string[];
    /** いま見ているファイル */
    file: string;
    /** 新しい順の記録。ファイルが大きいと読むのに時間がかかるので後から届く */
    entries?: LogEntry[];
    /** ファイルが1つも無いときだけ届く。なぜ無いのかの手がかり */
    diagnosis: LogDiagnosis | null;
}

interface LogDiagnosis {
    /** storage/logs に書き込めるか */
    writable: boolean;
    /** ファイルに書くチャンネルを使っているか */
    toFile: boolean;
    /** 使っているチャンネル (stack は開いたもの) */
    channel: string;
    /** ファイルに書く最低のレベル */
    level: string;
}

/** これより上のレベルだと、外部ログインの失敗などの warning が捨てられる */
const DROPS_WARNING = ['error', 'critical', 'alert', 'emergency'];

/**
 * アプリケーションログ。
 *
 * 本番は共用サーバでシェルに入れないので、ここから読めないと例外の中身を
 * 確かめる手段が無い。**監査ログとは別物**（あちらは誰に何が起きたかの記録）。
 */
const Index = ({ files, file, entries, diagnosis }: IndexProps) => {
    const { logCleared } = usePage().props.flash;
    const { ask, dialog } = useConfirm();

    const clear = (): void => {
        ask({
            title: t('admin.logs.clear_title'),
            description: t('admin.logs.clear_description'),
            confirmText: t('admin.logs.clear_confirm'),
            onConfirm: () => router.post('/admin/logs/clear', { file }),
        });
    };

    return (
        <AppLayout
            title={t('admin.logs.title')}
            lead={t('admin.logs.lead')}
            crumbs={[
                { label: t('admin.index.title'), href: '/admin' },
                { label: t('admin.logs.crumb') },
            ]}
        >
            {logCleared && <Alert severity="success" sx={{ mb: 2 }}>{t('admin.logs.cleared')}</Alert>}

            {diagnosis && <NoLogFile diagnosis={diagnosis} />}

            {files.length > 1 && (
                <TextField
                    select
                    size="small"
                    label={t('admin.logs.file')}
                    value={file}
                    // ファイル一覧は切り替えても変わらないので取り直さない
                    onChange={(event) => router.get(
                        '/admin/logs',
                        { file: event.target.value },
                        { only: ['file', 'entries'], preserveState: true },
                    )}
                    sx={{ minWidth: 280 }}
                >
                    {files.map((name) => (
                        <MenuItem key={name} value={name}>
                            {name}
                        </MenuItem>
                    ))}
                </TextField>
            )}

            <Deferred
                data="entries"
                fallback={(
                    <>
                        <SectionTitle>{t('admin.logs.heading')}</SectionTitle>
                        <LogListSkeleton />
                    </>
                )}
            >
                <LogEntries entries={entries ?? []} />
            </Deferred>

            <Stack
                direction="row"
                spacing={2}
                sx={{ mt: 1.5, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}
            >
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                    {t('admin.logs.tail_note')}
                </Typography>

                {file !== '' && (
                    <Button variant="outlined" color="error" onClick={clear}>
                        {t('admin.logs.clear')}
                    </Button>
                )}
            </Stack>

            {dialog}
        </AppLayout>
    );
};

/**
 * ログファイルが1つも無いときの手がかり。壊れていないのか、書けていないのかを見分ける。
 *
 * @param diagnosis サーバが調べた設定
 */
const NoLogFile = ({ diagnosis }: { diagnosis: LogDiagnosis }) => {
    const reasons: string[] = [];
    if (!diagnosis.writable) reasons.push(t('admin.logs.not_writable'));
    if (!diagnosis.toFile) reasons.push(t('admin.logs.not_to_file', { channel: diagnosis.channel }));
    if (diagnosis.toFile && DROPS_WARNING.includes(diagnosis.level.toLowerCase())) {
        reasons.push(t('admin.logs.drops_warning', { level: diagnosis.level }));
    }

    return (
        <Alert severity={reasons.length === 0 ? 'info' : 'warning'} sx={{ mb: 2 }}>
            {t('admin.logs.no_file')}
            {reasons.map((reason) => <div key={reason}>{reason}</div>)}
        </Alert>
    );
};

/**
 * 読み込み済みのログ一覧。
 *
 * @param entries 新しい順の記録
 */
const LogEntries = ({ entries }: { entries: LogEntry[] }) => {
    return (
        <>
            <SectionTitle note={t('admin.logs.count', { count: entries.length })}>
                {t('admin.logs.heading')}
            </SectionTitle>

            <Paper variant="outlined">
                {entries.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('admin.logs.empty')}
                    </Typography>
                )}

                <Stack divider={<RowDivider />}>
                    {entries.map((entry, index) => (
                        <LogEntryRow key={`${entry.at}-${String(index)}`} entry={entry} />
                    ))}
                </Stack>
            </Paper>
        </>
    );
};

export default Index;
