import { router, useForm } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import FormControlLabel from "@mui/material/FormControlLabel";
import MenuItem from "@mui/material/MenuItem";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Switch from "@mui/material/Switch";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import type { FormEvent } from "react";
import AppLayout from "../../../Components/AppLayout";
import { useConfirm } from "../../../lib/confirm";
import Icon from "../../../Components/Icon";
import SectionTitle from "../../../Components/SectionTitle";
import { t } from "../../../lib/i18n";
import type { OAuthClient } from "../../../types";

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
    const { data, setData, post, processing, errors, transform } = useForm<{
        name: string;
        redirect_uris: string;
        scopes: string;
        trust: string;
        icon_url: string;
        skips_consent: boolean;
        can_provision: boolean;
        is_confidential: boolean;
    }>({
        name: client?.name ?? "",
        // 1行1つで編集させる。配列を UI に出すより間違いが起きにくい
        redirect_uris: (client?.redirectUris ?? []).join("\n"),
        scopes: client?.scopes ?? "openid profile email",
        trust: client?.trust ?? "unapproved",
        icon_url: client?.iconUrl ?? "",
        skips_consent: client?.skipsConsent ?? false,
        can_provision: client?.canProvision ?? false,
        is_confidential: client?.isConfidential ?? true,
    });

    // 画面では1行1つ、サーバへは配列で渡す
    transform((form) => ({
        ...form,
        redirect_uris: form.redirect_uris
            .split(/\r?\n/)
            .map((uri) => uri.trim())
            .filter((uri) => uri !== ""),
    }));

    const { ask, dialog } = useConfirm();

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post(isNew ? "/admin/clients" : `/admin/clients/${client.id}`);
    };

    const remove = (): void => {
        if (client === null) return;

        ask({
            title: t('admin.clients.form.remove.title', { name: client.name }),
            description: t('admin.clients.form.remove.description'),
            confirmText: t('admin.clients.form.remove.confirm'),
            expected: client.name,
            onConfirm: () => router.post(`/admin/clients/${client.id}/delete`),
        });
    };

    const rotate = (): void => {
        if (client === null) return;

        ask({
            title: t('admin.clients.form.rotate.title'),
            description: t('admin.clients.form.rotate.description'),
            confirmText: t('admin.clients.form.rotate.confirm'),
            onConfirm: () => router.post(`/admin/clients/${client.id}/secret`),
        });
    };

    return (
        <AppLayout
            title={isNew ? t('admin.clients.form.new_title') : client.name}
            crumbs={[
                { label: "ChreeID", href: "/" },
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

                        <TextField
                            label={t('admin.clients.form.fields.redirect_uris')}
                            value={data.redirect_uris}
                            onChange={(e) =>
                                setData("redirect_uris", e.target.value)
                            }
                            error={Boolean(errors.redirect_uris)}
                            helperText={
                                errors.redirect_uris ??
                                t('admin.clients.form.fields.redirect_uris_helper')
                            }
                            multiline
                            minRows={3}
                            required
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
                            label={t('admin.clients.form.fields.icon_url')}
                            value={data.icon_url}
                            onChange={(e) =>
                                setData("icon_url", e.target.value)
                            }
                            error={Boolean(errors.icon_url)}
                            helperText={
                                errors.icon_url ??
                                t('admin.clients.form.fields.icon_url_helper')
                            }
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

                        <FormControlLabel
                            control={
                                <Switch
                                    checked={data.skips_consent}
                                    onChange={(e) =>
                                        setData(
                                            "skips_consent",
                                            e.target.checked,
                                        )
                                    }
                                />
                            }
                            label={t('admin.clients.form.fields.skips_consent')}
                        />

                        <FormControlLabel
                            control={
                                <Switch
                                    checked={data.can_provision}
                                    onChange={(e) =>
                                        setData(
                                            "can_provision",
                                            e.target.checked,
                                        )
                                    }
                                />
                            }
                            label={t('admin.clients.form.fields.can_provision')}
                        />

                        {isNew && (
                            <FormControlLabel
                                control={
                                    <Switch
                                        checked={data.is_confidential}
                                        onChange={(e) =>
                                            setData(
                                                "is_confidential",
                                                e.target.checked,
                                            )
                                        }
                                    />
                                }
                                label={t('admin.clients.form.fields.is_confidential')}
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

            {!isNew && (
                <>
                    <SectionTitle>{t('admin.clients.form.actions.heading')}</SectionTitle>
                    <Paper variant="outlined" sx={{ p: 2 }}>
                        <Stack spacing={1.5} sx={{ alignItems: "flex-start" }}>
                            {client.isConfidential && (
                                <Button
                                    variant="outlined"
                                    color="inherit"
                                    startIcon={<Icon name="rotate" />}
                                    onClick={rotate}
                                >
                                    {t('admin.clients.form.actions.rotate')}
                                </Button>
                            )}
                            <Button
                                variant="outlined"
                                color="error"
                                startIcon={<Icon name="trash" />}
                                onClick={remove}
                            >
                                {t('admin.clients.form.actions.remove')}
                            </Button>
                        </Stack>
                    </Paper>
                </>
            )}

            {dialog}
        </AppLayout>
    );
}
