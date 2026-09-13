import { router, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import Icon from '../../../Components/Icon';
import SectionTitle from '../../../Components/SectionTitle';
import { useConfirm } from '../../../lib/confirm';
import { formatDateTime } from '../../../lib/datetime';
import { t } from '../../../lib/i18n';

interface BackupFile {
    id: string;
    name: string;
    createdTime: string;
}

interface IndexProps {
    /** 暗号化の鍵があるか */
    hasKey: boolean;
    /** Google Drive の設定が4つ揃っているか */
    hasDrive: boolean;
    /** サーバ内にも置くか */
    hasLocal: boolean;
    /** Drive に残す世代数 */
    keep: number;
    /** サーバ内に残す日数 */
    localDays: number;
    backups: BackupFile[];
    localBackups: BackupFile[];
    /** 一覧を引けなかった理由。引けていれば null */
    listError: string | null;
}

/**
 * 置き場ごとの控えの一覧。
 */
function BackupList({ heading, files }: { heading: string; files: BackupFile[] }) {
    return (
        <>
            <SectionTitle note={t('admin.backups.count', { count: files.length })}>{heading}</SectionTitle>
            <Paper variant="outlined" sx={{ mb: 2 }}>
                {files.length === 0 ? (
                    <Box sx={{ px: 2, py: 1.5 }}>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.disabled' }}>
                            {t('admin.backups.list.empty')}
                        </Typography>
                    </Box>
                ) : (
                    files.map((file) => (
                        <Box
                            key={file.id}
                            sx={{
                                px: 2,
                                py: 1.25,
                                borderTop: '1px solid',
                                borderColor: 'divider',
                                '&:first-of-type': { borderTop: 'none' },
                            }}
                        >
                            <Typography sx={{ fontSize: '0.9375rem' }}>{file.name}</Typography>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                {formatDateTime(file.createdTime) ?? file.createdTime}
                            </Typography>
                        </Box>
                    ))
                )}
            </Paper>
        </>
    );
}

export default function Index({ hasKey, hasDrive, hasLocal, keep, localDays, backups, localBackups, listError }: IndexProps) {
    const { flash, errors } = usePage().props;
    const { ask, dialog } = useConfirm();
    const [running, setRunning] = useState(false);

    const ready = hasKey && (hasDrive || hasLocal);

    const run = (): void => {
        ask({
            title: t('admin.backups.run.title'),
            description: t('admin.backups.run.description'),
            confirmText: t('admin.backups.run.confirm'),
            onConfirm: () => {
                setRunning(true);
                router.post('/admin/backups', {}, { onFinish: () => setRunning(false) });
            },
        });
    };

    return (
        <AppLayout
            title={t('admin.backups.title')}
            crumbs={[
                { label: t('admin.crumb'), href: '/admin' },
                { label: t('admin.backups.crumb') },
            ]}
        >
            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', mb: 2 }}>
                {t('admin.backups.lead')}
            </Typography>

            {errors.backup && <Alert severity="error" sx={{ mb: 1.5 }}>{errors.backup}</Alert>}

            {flash.backupTaken && (
                <Alert severity="success" sx={{ mb: 1.5 }}>
                    {t('admin.backups.taken', {
                        name: flash.backupTaken.name,
                        tables: flash.backupTaken.tables,
                        rows: flash.backupTaken.rows,
                    })}
                </Alert>
            )}

            {/* 何が足りないかを分けて出す。ひとまとめの「使えません」では直しようがない */}
            {!hasKey && <Alert severity="warning" sx={{ mb: 1.5 }}>{t('admin.backups.no_key')}</Alert>}
            {!hasDrive && <Alert severity="warning" sx={{ mb: 1.5 }}>{t('admin.backups.no_drive')}</Alert>}
            {!hasLocal && <Alert severity="warning" sx={{ mb: 1.5 }}>{t('admin.backups.no_local')}</Alert>}
            {listError !== null && <Alert severity="error" sx={{ mb: 1.5 }}>{listError}</Alert>}

            <Paper variant="outlined" sx={{ p: 2, mb: 2 }}>
                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('admin.backups.keep_note', { count: keep })}
                        <br />
                        {t('admin.backups.local_keep_note', { count: localDays })}
                    </Typography>
                    <Button
                        variant="outlined"
                        color="inherit"
                        startIcon={<Icon name="cloud-arrow-up" />}
                        disabled={!ready || running}
                        onClick={run}
                    >
                        {t('admin.backups.run.button')}
                    </Button>
                </Stack>
            </Paper>

            {dialog}

            {hasLocal && <BackupList heading={t('admin.backups.local_list.heading')} files={localBackups} />}
            <BackupList heading={t('admin.backups.list.heading')} files={backups} />
        </AppLayout>
    );
}
