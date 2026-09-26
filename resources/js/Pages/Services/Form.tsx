import { useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import type { FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import SectionTitle from '../../Components/SectionTitle';
import ServiceUrlFields from '../../Components/Services/ServiceUrlFields';
import { t } from '../../lib/i18n';
import type { OwnedService } from '../../types';

interface FormProps {
    /** 編集なら対象。新規登録なら null */
    service: OwnedService | null;
    /** 第三者に渡すスコープ。選ばせない */
    scopes: string;
}

/**
 * サービスの登録と編集。
 *
 * 信頼状態・同意省略・サービスアカウントの発行権限は置かない。
 * **運営しか動かせないものを、触れそうな見た目で出さない。**
 */
export default function Form({ service, scopes }: FormProps) {
    const isNew = service === null;

    const form = useForm({
        name: service?.name ?? '',
        redirect_uris: service?.redirectUris ?? [''],
        icon_url: service?.iconUrl ?? '',
        settings_url: service?.settingsUrl ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.post(isNew ? '/services/register' : `/services/${service.id}/edit`);
    };

    const title = isNew ? t('services.form.title') : t('services.form.edit_title');

    return (
        <AppLayout
            title={title}
            crumbs={[
                { label: t('services.crumb'), href: '/services' },
                { label: title },
            ]}
        >
            <Box component="form" onSubmit={submit} noValidate>
                {isNew && <Alert severity="info">{t('services.form.trust_note')}</Alert>}

                <SectionTitle>{title}</SectionTitle>
                <Paper variant="outlined" sx={{ p: 2 }}>
                    <Stack spacing={2}>
                        <TextField
                            label={t('services.form.name')}
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={Boolean(form.errors.name)}
                            helperText={form.errors.name}
                            required
                        />

                        <ServiceUrlFields
                            data={form.data}
                            errors={form.errors}
                            onChange={(changed) => form.setData({ ...form.data, ...changed })}
                        />

                        <TextField
                            label={t('services.form.scopes')}
                            value={scopes}
                            helperText={t('services.form.scopes_hint')}
                            slotProps={{ input: { readOnly: true } }}
                        />

                        <Box>
                            <Button type="submit" variant="contained" disabled={form.processing}>
                                {isNew ? t('services.form.submit') : t('services.form.save')}
                            </Button>
                        </Box>
                    </Stack>
                </Paper>
            </Box>
        </AppLayout>
    );
}
