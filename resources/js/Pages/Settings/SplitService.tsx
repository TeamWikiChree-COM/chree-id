import { useForm } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import type { FormEvent } from "react";
import AuthLayout from "../../Components/AuthLayout";

/** 分離先へ複製できる認証方法 */
interface SplitOption {
    id: string;
    type: string;
}

/** 画面に出す名前。移せないものはサーバ側で候補から外れている */
const CREDENTIAL_LABELS: Record<string, string> = {
    password: "パスワード",
    totp: "認証アプリ (2段階認証)",
    magic_link: "メールでログイン",
    oauth: "Google 連携",
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

    const toggle = (id: string): void => {
        setData(
            "credentials",
            data.credentials.includes(id)
                ? data.credentials.filter((chosen) => chosen !== id)
                : [...data.credentials, id],
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post("/services/split");
    };

    return (
        <AuthLayout title="サービスを外す" heading="サービスを外す">
            <Typography variant="body2" color="text.secondary">
                {serviceName}
                {serviceUserId !== null && `（${serviceUserId}）`}
                を、この ChreeID のまとめから外して単独のアカウントに戻します。
                {serviceName} 側のアカウントや利用状況はそのまま残ります。
            </Typography>

            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    <Box
                        sx={{
                            border: "1px solid",
                            borderColor: "divider",
                            borderRadius: 2,
                            p: 2,
                        }}
                    >
                        <Typography variant="body2" sx={{ mb: 1 }}>
                            外したあとのログイン方法を選んでください
                        </Typography>
                        {options.map((option) => (
                            <FormControlLabel
                                key={option.id}
                                control={
                                    <Checkbox
                                        checked={data.credentials.includes(option.id)}
                                        onChange={() => toggle(option.id)}
                                    />
                                }
                                label={CREDENTIAL_LABELS[option.type] ?? option.type}
                                sx={{ display: "flex" }}
                            />
                        ))}
                        <Typography variant="body2" color="text.secondary">
                            選んだものは複製され、この ChreeID からは無くなりません。
                            パスキーは持っていけないので、必要なら外したあとに登録し直してください。
                        </Typography>
                        {errors.credentials && (
                            <Alert severity="error" sx={{ mt: 1 }}>
                                {errors.credentials}
                            </Alert>
                        )}
                    </Box>

                    <TextField
                        label="メールアドレス"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                        error={Boolean(errors.email)}
                        helperText={errors.email ?? "空のままなら、いまのアドレスを引き継ぎます"}
                        autoComplete="email"
                    />

                    <TextField
                        label="表示名"
                        value={data.display_name}
                        onChange={(e) => setData("display_name", e.target.value)}
                        error={Boolean(errors.display_name)}
                        helperText={errors.display_name ?? "空のままなら、いまの表示名を引き継ぎます"}
                        autoComplete="nickname"
                    />

                    <Button type="submit" variant="contained" color="error" disabled={processing}>
                        まとめから外す
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
