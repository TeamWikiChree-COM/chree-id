import { useForm, usePage } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import MenuItem from "@mui/material/MenuItem";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import type { FormEvent } from "react";
import AppLayout from "../../../Components/AppLayout";
import SwitchField from "../../../Components/SwitchField";
import ServiceUrlFields from "../../../Components/Services/ServiceUrlFields";
import { localeLabel, t } from "../../../lib/i18n";
import type { OAuthClient } from "../../../types";
import ClientActions from "./ClientActions";

interface TrustOption {
    value: string;
    label: string;
}

interface FormProps {
    /** 新規登録なら null */
    client: OAuthClient | null;
    trustOptions: TrustOption[];
}

export default function Form({ client, trustOptions }: FormProps) {
    const isNew = client === null;

    // trust は select の値なので string で持つ。妥当性はサーバ側 (ServiceTrust) で確かめる
    const { locales } = usePage().props;

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        names: Record<string, string>;
        redirect_uris: string[];
        scopes: string;
        trust: string;
        icon_url: string;
        settings_url: string;
        skips_consent: boolean;
        can_provision: boolean;
        is_confidential: boolean;
    }>({
        name: client?.name ?? "",
        names: client?.names ?? {},
        redirect_uris: client?.redirectUris ?? [""],
        scopes: client?.scopes ?? "openid profile email",
        trust: client?.trust ?? "unapproved",
        icon_url: client?.iconUrl ?? "",
        settings_url: client?.settingsUrl ?? "",
        skips_consent: client?.skipsConsent ?? false,
        can_provision: client?.canProvision ?? false,
        is_confidential: client?.isConfidential ?? true,
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post(isNew ? "/admin/clients" : `/admin/clients/${client.id}`);
    };

    return (
        <AppLayout
            title={isNew ? t('admin.clients.form.new_title') : client.name}
            crumbs={[
                { label: t('admin.crumb'), href: "/admin" },
                { label: t('admin.clients.crumb'), href: "/admin/clients" },
                { label: isNew ? t('admin.clients.form.crumb.create') : t('admin.clients.form.crumb.edit') },
            ]}
        >
            {!isNew && (
                <Alert severity="info" sx={{ mb: 2 }}>
                    <Box component="code" sx={{ wordBreak: "break-all" }}>
                        {client.id}
                    </Box>
                </Alert>
            )}

            <Paper variant="outlined" sx={{ p: 2 }}>
                <Box component="form" onSubmit={submit} noValidate>
                    <Stack spacing={2}>
                        <TextField
                            label={t('admin.clients.form.fields.name')}
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            error={Boolean(errors.name)}
                            helperText={errors.name ?? t('admin.clients.form.fields.name_helper')}
                            autoFocus
                            required
                        />

                        {locales.map((locale) => (
                            <TextField
                                key={locale}
                                label={t('admin.clients.form.fields.name_for', { language: localeLabel(locale) })}
                                value={data.names[locale] ?? ''}
                                onChange={(e) => setData('names', { ...data.names, [locale]: e.target.value })}
                                helperText={t('admin.clients.form.fields.name_for_helper')}
                            />
                        ))}

                        <ServiceUrlFields
                            data={data}
                            errors={errors}
                            onChange={(changed) => setData({ ...data, ...changed })}
                        />

                        <TextField
                            label={t('admin.clients.form.fields.scopes')}
                            value={data.scopes}
                            onChange={(e) => setData("scopes", e.target.value)}
                            error={Boolean(errors.scopes)}
                            helperText={errors.scopes ?? t('admin.clients.form.fields.scopes_helper')}
                            required
                        />

                        <TextField
                            label={t('admin.clients.form.fields.trust')}
                            value={data.trust}
                            onChange={(e) => setData("trust", e.target.value)}
                            error={Boolean(errors.trust)}
                            helperText={errors.trust}
                            select
                        >
                            {trustOptions.map((option) => (
                                <MenuItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </MenuItem>
                            ))}
                        </TextField>

                        <SwitchField
                            label={t('admin.clients.form.fields.skips_consent')}
                            checked={data.skips_consent}
                            onChange={(checked) => setData("skips_consent", checked)}
                        />

                        <SwitchField
                            label={t('admin.clients.form.fields.can_provision')}
                            checked={data.can_provision}
                            onChange={(checked) => setData("can_provision", checked)}
                        />

                        {isNew && (
                            <SwitchField
                                label={t('admin.clients.form.fields.is_confidential')}
                                checked={data.is_confidential}
                                onChange={(checked) => setData("is_confidential", checked)}
                            />
                        )}

                        <Box>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={processing}
                            >
                                {isNew ? t('admin.clients.form.submit.create') : t('admin.clients.form.submit.edit')}
                            </Button>
                        </Box>
                    </Stack>
                </Box>
            </Paper>

            {!isNew && <ClientActions client={client} />}
        </AppLayout>
    );
}
