import { useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import type { FormEvent } from "react";
import AuthLayout from "../../Components/AuthLayout";
import CredentialPicker from "../../Components/CredentialPicker";
import { t } from "../../lib/i18n";

/** 分離先へ複製できる認証方法 */
interface SplitOption {
    id: string;
    type: string;
}

/** 画面に出す名前。移せないものはサーバ側で候補から外れている */
const CREDENTIAL_LABELS: Record<string, string> = {
    password: t("settings.split.credential.password"),
    totp: t("settings.split.credential.totp"),
    magic_link: t("settings.split.credential.magic_link"),
    oauth: t("settings.split.credential.oauth"),
};

interface SplitServiceProps {
    /** 外すサービスアカウントのID */
    serviceAccountId: string;
    /** サービス名 */
    serviceName: string;
    /** サービス側での識別子 */
    serviceUserId: string | null;
    /** 持っていける認証方法。既定は全部オン */
    options: SplitOption[];
}

/**
 * サービスをまとめから外す画面。
 *
 * 統合の逆操作。サービスから見た識別子は変わらないので、
 * 向こうのアカウントや利用状況はそのまま残る。
 */
export default function SplitService({
    serviceAccountId,
    serviceName,
    serviceUserId,
    options,
}: SplitServiceProps) {
    const { data, setData, post, processing, errors } = useForm<{
        service_account_id: string;
        email: string;
        display_name: string;
        credentials: string[];
    }>({
        service_account_id: serviceAccountId,
        email: "",
        display_name: "",
        credentials: options.map((option) => option.id),
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post("/services/split");
    };

    return (
        <AuthLayout title={t("settings.split.title")} heading={t("settings.split.title")}>
            <Typography variant="body2" color="text.secondary">
                {serviceName}
                {serviceUserId !== null && `（${serviceUserId}）`}
                {t("settings.split.description_line1")}
                {serviceName}
                {t("settings.split.description_line2")}
            </Typography>

            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    <CredentialPicker
                        options={options}
                        selected={data.credentials}
                        onChange={(selected) => setData("credentials", selected)}
                        instruction={t("settings.split.credentials.instruction")}
                        note={t("settings.split.credentials.note")}
                        labels={CREDENTIAL_LABELS}
                        error={errors.credentials}
                    />

                    <TextField
                        label={t("settings.split.email.label")}
                        type="email"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                        error={Boolean(errors.email)}
                        helperText={errors.email ?? t("settings.split.email.hint")}
                        autoComplete="email"
                    />

                    <TextField
                        label={t("settings.split.display_name.label")}
                        value={data.display_name}
                        onChange={(e) => setData("display_name", e.target.value)}
                        error={Boolean(errors.display_name)}
                        helperText={errors.display_name ?? t("settings.split.display_name.hint")}
                        autoComplete="nickname"
                    />

                    <Button type="submit" variant="contained" color="error" disabled={processing}>
                        {t("settings.split.submit")}
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
