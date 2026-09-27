import { router, useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import SectionTitle from '@/Components/SectionTitle';
import { useConfirm } from '@/lib/confirm';
import { formatDateTime } from '@/lib/datetime';
import { errorText } from '../lib/errors';
import { t } from '../lib/i18n';

export interface SigningKey {
    /** 証明書の CN */
    subject: string;
    expiresAt: string;
}

interface KeySectionProps {
    signingKey: SigningKey | null;
    metadataUrl: string;
}

/**
 * 署名の鍵。作る、作り直す、手元で作ったものを取り込む。
 *
 * 作り直しと、鍵があるときの取り込みは確認を挟む。登録済みのすべてのサービスで設定のやり直しが要るため。
 */
export default function KeySection({ signingKey, metadataUrl }: KeySectionProps) {
    const { errors } = usePage().props;
    const { ask, dialog } = useConfirm();
    const exists = signingKey !== null;

    const importForm = useForm({ private_key: '', certificate: '', replace: exists });

    const confirmReplace = (onConfirm: () => void): void => {
        if (!exists) return onConfirm();

        ask({
            title: t('admin.key.replace_title'),
            description: t('admin.key.replace_description'),
            confirmText: t('admin.key.replace_confirm'),
            onConfirm,
        });
    };

    const generate = (): void => confirmReplace(() => router.post('/plugins/saml/admin/key', { replace: exists }));

    const submitImport = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        confirmReplace(() => importForm.post('/plugins/saml/admin/key/import'));
    };

    return (
        <>
            <SectionTitle>{t('admin.key.heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                    {errors.key !== undefined && <Alert severity="error">{errorText(errors.key)}</Alert>}

                    {signingKey === null ? (
                        <Typography variant="body2">{t('admin.key.empty')}</Typography>
                    ) : (
                        <Box>
                            <Typography variant="body2">{signingKey.subject}</Typography>
                            <Typography variant="body2" color="text.secondary">
                                {t('admin.key.expires', { date: String(formatDateTime(signingKey.expiresAt)) })}
                            </Typography>
                            <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                                {t('admin.key.metadata')}
                            </Typography>
                            <Box component="code" sx={{ fontSize: '0.8125rem', wordBreak: 'break-all' }}>
                                {metadataUrl}
                            </Box>
                        </Box>
                    )}

                    <Button variant={exists ? 'outlined' : 'contained'} color={exists ? 'inherit' : 'primary'} onClick={generate}>
                        {exists ? t('admin.key.replace') : t('admin.key.create')}
                    </Button>
                </Stack>
            </Paper>

            <SectionTitle note={t('admin.key.import_lead')}>{t('admin.key.import_heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={2} component="form" onSubmit={submitImport} noValidate>
                    {errors.import !== undefined && <Alert severity="error">{errorText(errors.import)}</Alert>}
                    <TextField
                        label={t('admin.key.import_key')}
                        value={importForm.data.private_key}
                        onChange={(e) => importForm.setData('private_key', e.target.value)}
                        multiline
                        minRows={3}
                    />
                    <TextField
                        label={t('admin.key.import_certificate')}
                        value={importForm.data.certificate}
                        onChange={(e) => importForm.setData('certificate', e.target.value)}
                        multiline
                        minRows={3}
                    />
                    <Box>
                        <Button type="submit" variant="outlined" color="inherit" disabled={importForm.processing}>
                            {t('admin.key.import')}
                        </Button>
                    </Box>
                </Stack>
            </Paper>
            {dialog}
        </>
    );
}
