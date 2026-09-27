import { router, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import type { FormEvent } from 'react';
import AppLayout from '@/Components/AppLayout';
import Icon from '@/Components/Icon';
import SectionTitle from '@/Components/SectionTitle';
import { useConfirm } from '@/lib/confirm';
import { t as core } from '@/lib/i18n';
import { errorText } from '../../lib/errors';
import { t } from '../../lib/i18n';

interface ProviderInput {
    id: number;
    clientId: string;
    entityId: string;
    acsUrl: string;
    certificate: string;
    scopes: string;
}

interface FormProps {
    /** 新規なら null */
    provider: ProviderInput | null;
    /** 選べるサービス (oauth_clients) */
    clients: { id: string; name: string }[];
}

/**
 * SAML でつなぐサービスの登録と変更。
 */
export default function Form({ provider, clients }: FormProps) {
    const isNew = provider === null;
    const { ask, dialog } = useConfirm();

    const { data, setData, post, processing, errors } = useForm({
        client_id: provider?.clientId ?? '',
        entity_id: provider?.entityId ?? '',
        acs_url: provider?.acsUrl ?? '',
        certificate: provider?.certificate ?? '',
        scopes: provider?.scopes ?? 'openid email profile',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post(isNew ? '/plugins/saml/admin/providers' : `/plugins/saml/admin/providers/${provider.id}`);
    };

    const remove = (): void => {
        if (isNew) return;

        ask({
            title: t('admin.form.remove'),
            description: t('admin.form.remove_description'),
            confirmText: t('admin.form.remove_confirm'),
            onConfirm: () => router.post(`/plugins/saml/admin/providers/${provider.id}/delete`),
        });
    };

    const title = isNew ? t('admin.form.create_title') : t('admin.form.edit_title');

    return (
        <AppLayout
            title={title}
            crumbs={[
                { label: core('admin.crumb'), href: '/admin' },
                { label: t('admin.crumb'), href: '/plugins/saml/admin' },
                { label: title },
            ]}
        >
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={2} component="form" onSubmit={submit} noValidate>
                    <TextField
                        label={t('admin.form.client')}
                        value={data.client_id}
                        onChange={(e) => setData('client_id', e.target.value)}
                        error={errors.client_id !== undefined}
                        helperText={errorText(errors.client_id) ?? t('admin.form.client_helper')}
                        select
                        required
                    >
                        {clients.map((client) => (
                            <MenuItem key={client.id} value={client.id}>
                                {client.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        label={t('admin.form.entity_id')}
                        value={data.entity_id}
                        onChange={(e) => setData('entity_id', e.target.value)}
                        error={errors.entity_id !== undefined}
                        helperText={errorText(errors.entity_id)}
                        required
                    />
                    <TextField
                        label={t('admin.form.acs_url')}
                        value={data.acs_url}
                        onChange={(e) => setData('acs_url', e.target.value)}
                        error={errors.acs_url !== undefined}
                        helperText={errorText(errors.acs_url) ?? t('admin.form.acs_url_helper')}
                        required
                    />
                    <TextField
                        label={t('admin.form.scopes')}
                        value={data.scopes}
                        onChange={(e) => setData('scopes', e.target.value)}
                        error={errors.scopes !== undefined}
                        helperText={errorText(errors.scopes) ?? t('admin.form.scopes_helper')}
                    />
                    <TextField
                        label={t('admin.form.certificate')}
                        value={data.certificate}
                        onChange={(e) => setData('certificate', e.target.value)}
                        error={errors.certificate !== undefined}
                        helperText={errorText(errors.certificate) ?? t('admin.form.certificate_helper')}
                        multiline
                        minRows={3}
                    />
                    <Box>
                        <Button type="submit" variant="contained" disabled={processing}>
                            {t('admin.form.save')}
                        </Button>
                    </Box>
                </Stack>
            </Paper>

            {!isNew && (
                <>
                    <SectionTitle>{t('admin.form.remove')}</SectionTitle>
                    <Paper variant="outlined" sx={{ p: 2 }}>
                        <Button variant="outlined" color="error" startIcon={<Icon name="trash" />} onClick={remove}>
                            {t('admin.form.remove')}
                        </Button>
                    </Paper>
                    {dialog}
                </>
            )}
        </AppLayout>
    );
}
