import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import AppLayout from '../../../Components/AppLayout';
import LogEntryRow from '../../../Components/LogEntryRow';
import SectionTitle from '../../../Components/SectionTitle';
import { useConfirm } from '../../../lib/confirm';
import { t } from '../../../lib/i18n';
import type { LogEntry } from '../../../types';

interface IndexProps {
    /** 読めるログファイル。新しい順 */
    files: string[];
    /** いま見ているファイル */
    file: string;
    /** 新しい順の記録 */
    entries: LogEntry[];
}

/**
 * アプリケーションログ。
 *
 * 本番は共用サーバでシェルに入れないので、ここから読めないと例外の中身を
 * 確かめる手段が無い。**監査ログとは別物**（あちらは誰に何が起きたかの記録）。
 */
export default function Index({ files, file, entries }: IndexProps) {
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
                { label: 'ChreeID', href: '/' },
                { label: t('admin.index.title'), href: '/admin' },
                { label: t('admin.logs.crumb') },
            ]}
        >
            {logCleared && <Alert severity="success" sx={{ mb: 2 }}>{t('admin.logs.cleared')}</Alert>}

            {files.length > 1 && (
                <TextField
                    select
                    size="small"
                    label={t('admin.logs.file')}
                    value={file}
                    onChange={(event) => router.get('/admin/logs', { file: event.target.value })}
                    sx={{ minWidth: 280 }}
                >
                    {files.map((name) => (
                        <MenuItem key={name} value={name}>
                            {name}
                        </MenuItem>
                    ))}
                </TextField>
            )}

            <SectionTitle note={t('admin.logs.count', { count: entries.length })}>
                {t('admin.logs.heading')}
            </SectionTitle>

            <Paper variant="outlined">
                {entries.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('admin.logs.empty')}
                    </Typography>
                )}

                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {entries.map((entry, index) => (
                        <LogEntryRow key={`${entry.at}-${String(index)}`} entry={entry} />
                    ))}
                </Stack>
            </Paper>

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
}
