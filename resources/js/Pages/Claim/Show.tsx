import { useForm, usePage } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import Stack from "@mui/material/Stack";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";
import type { FormEvent } from "react";
import AuthLayout from "../../Components/AuthLayout";
import Icon from "../../Components/Icon";
import PasswordField from "../../Components/PasswordField";
import { registerClaimPasskey } from "../../lib/passkey";
import { t } from "../../lib/i18n";

interface ClaimShowProps {
    /** サービスから渡された平文トークン。そのまま送り返す */
    token: string;
    /** 引き取り元のサービス名 */
    serviceName: string;
    /** 既に分かっている連絡先 */
    email: string | null;
    /** その連絡先の到達性が確認済みか */
    emailVerified: boolean;
    /** 既に分かっている表示名 */
    displayName: string | null;
    /** 移行元で既にパスワードを使っていたか。false ならパスワード以外を勧める */
    hasPassword: boolean;
    /** 移行元から引き継いだ認証手段。既定は全部オン */
    carried: CarriedCredential[];
}

/** 移行元から引き継いだ認証手段 */
interface CarriedCredential {
    id: string;
    type: string;
}

/** 画面に出す名前 */
const CREDENTIAL_LABELS: Record<string, string> = {
    password: t('claim.credential.password'),
    passkey: t('claim.credential.passkey'),
    totp: t('claim.credential.totp'),
    magic_link: t('claim.credential.magic_link'),
    oauth: t('claim.credential.oauth'),
};

/** ログイン方法が1つも無いときだけ、ここから決めてもらう */
type Method = "password" | "passkey" | "google";

/**
 * 移行 (claim) の画面。
 *
 * **移行元の認証手段は発行時に引き継いでいる**ので、たいていは何も決めさせない。
 * 決めてもらうのは、引き継ぐものが1つも無かった場合だけ
 * (Google だけで使っていた等)。ここを取り違えると、既に入れる人に
 * わざわざパスワードを作り直させることになる。
 */
export default function ClaimShow({
    token,
    serviceName,
    email,
    emailVerified,
    displayName,
    hasPassword,
    carried,
}: ClaimShowProps) {
    const { externalIdps } = usePage().props;
    const googleAvailable = externalIdps.includes("google");

    const initialMethod: Method =
        hasPassword || !googleAvailable ? "password" : "google";
    const [method, setMethod] = useState<Method>(initialMethod);
    const [passkeyError, setPasskeyError] = useState<string | null>(null);
    const [passkeyRegistered, setPasskeyRegistered] = useState(false);
    const [passkeyBusy, setPasskeyBusy] = useState(false);

    const hasCarried = carried.length > 0;

    const { data, setData, post, processing, errors } = useForm<{
        token: string;
        method: Method | "existing";
        password: string;
        display_name: string;
        email: string;
        credentials: string[];
    }>({
        token,
        // 引き継ぐものがあるなら、ログイン方法は決め直させない
        method: hasCarried ? "existing" : initialMethod,
        password: "",
        display_name: displayName ?? "",
        email: email ?? "",
        credentials: carried.map((credential) => credential.id),
    });

    const toggle = (id: string): void => {
        setData(
            "credentials",
            data.credentials.includes(id)
                ? data.credentials.filter((chosen) => chosen !== id)
                : [...data.credentials, id],
        );
    };

    const changeMethod = (next: Method): void => {
        setMethod(next);
        setData("method", next);
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post("/claim");
    };

    const addPasskey = async (): Promise<void> => {
        setPasskeyError(null);
        setPasskeyBusy(true);
        try {
            await registerClaimPasskey(token);
            setPasskeyRegistered(true);
        } catch (e) {
            setPasskeyError(
                e instanceof Error
                    ? e.message
                    : t('claim.show.passkey.register_failed'),
            );
        } finally {
            setPasskeyBusy(false);
        }
    };

    const emailField =
        email !== null && emailVerified ? (
            <TextField
                label={t('auth.common.email_label')}
                value={email}
                slotProps={{ input: { readOnly: true } }}
            />
        ) : (
            <TextField
                label={t('auth.common.email_label')}
                type="email"
                value={data.email}
                onChange={(e) => setData("email", e.target.value)}
                error={Boolean(errors.email)}
                helperText={errors.email ?? t('claim.show.email_hint')}
                autoComplete="email"
                required
            />
        );

    const displayNameField = (
        <TextField
            label={t('auth.common.display_name_label')}
            value={data.display_name}
            onChange={(e) => setData("display_name", e.target.value)}
            error={Boolean(errors.display_name)}
            helperText={errors.display_name ?? t('auth.common.display_name_hint')}
            autoComplete="nickname"
        />
    );

    const intro = (
        <Typography variant="body2" color="text.secondary">
            {t('claim.choose.description', { serviceName })}
        </Typography>
    );

    const errorAlert = errors.token && (
        <Alert severity="error">{errors.token}</Alert>
    );

    // 引き継ぐものがある。ログイン方法は決め直させず、どれを引き継ぐかだけ選ばせる
    if (hasCarried) {
        const keepsNothing = data.credentials.length === 0;

        return (
            <AuthLayout title={t('claim.show.title')} heading={t('claim.show.title')}>
                {intro}

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
                                {t('claim.merge_panel.select_credentials')}
                            </Typography>
                            {carried.map((credential) => (
                                <FormControlLabel
                                    key={credential.id}
                                    control={
                                        <Checkbox
                                            checked={data.credentials.includes(
                                                credential.id,
                                            )}
                                            onChange={() =>
                                                toggle(credential.id)
                                            }
                                        />
                                    }
                                    label={
                                        CREDENTIAL_LABELS[credential.type] ??
                                        credential.type
                                    }
                                    sx={{ display: "flex" }}
                                />
                            ))}
                            <Typography variant="body2" color="text.secondary">
                                {t('claim.show.carried.unchecked_note', { serviceName })}
                            </Typography>
                            {errors.method && (
                                <Alert severity="error" sx={{ mt: 1 }}>
                                    {errors.method}
                                </Alert>
                            )}
                        </Box>

                        {keepsNothing && (
                            <Alert severity="warning">
                                {t('claim.show.carried.keeps_nothing_warning')}
                            </Alert>
                        )}

                        {emailField}
                        {displayNameField}
                        {errorAlert}
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={processing || keepsNothing}
                        >
                            {t('claim.show.submit')}
                        </Button>
                    </Stack>
                </Box>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={t('claim.show.title')} heading={t('claim.show.title')}>
            {intro}

            <Alert severity="info">
                {t('claim.show.no_method_warning')}
            </Alert>

            <Tabs
                value={method}
                onChange={(_, next: Method) => changeMethod(next)}
                variant="fullWidth"
            >
                <Tab value="password" label={t('claim.credential.password')} />
                <Tab value="passkey" label={t('claim.credential.passkey')} />
                {googleAvailable && <Tab value="google" label="Google" />}
            </Tabs>

            {method === "google" ? (
                <Stack spacing={2}>
                    {emailField}
                    <Typography variant="body2" color="text.secondary">
                        {t('claim.show.google.description')}
                    </Typography>
                    <Button
                        component="a"
                        href={`/auth/google/redirect?claim_token=${encodeURIComponent(token)}`}
                        variant="contained"
                        startIcon={<Icon name="google" family="brands" />}
                    >
                        {t('claim.show.google.continue')}
                    </Button>
                </Stack>
            ) : (
                <Box component="form" onSubmit={submit} noValidate>
                    <Stack spacing={2}>
                        {emailField}

                        {method === "password" && (
                            <PasswordField
                                label={t('claim.credential.password')}
                                value={data.password}
                                onChange={(e) =>
                                    setData("password", e.target.value)
                                }
                                error={Boolean(errors.password)}
                                helperText={errors.password ?? t('auth.common.password_min_length')}
                                autoComplete="new-password"
                                required
                            />
                        )}

                        {method === "passkey" && (
                            <Stack spacing={1}>
                                {passkeyError && (
                                    <Alert severity="error">
                                        {passkeyError}
                                    </Alert>
                                )}
                                {errors.method && (
                                    <Alert severity="error">
                                        {errors.method}
                                    </Alert>
                                )}
                                {passkeyRegistered ? (
                                    <Alert severity="success">
                                        {t('claim.show.passkey.registered')}
                                    </Alert>
                                ) : (
                                    <Button
                                        variant="outlined"
                                        color="inherit"
                                        startIcon={<Icon name="fingerprint" />}
                                        disabled={passkeyBusy}
                                        onClick={() => void addPasskey()}
                                    >
                                        {t('claim.show.passkey.register')}
                                    </Button>
                                )}
                            </Stack>
                        )}

                        {displayNameField}
                        {errorAlert}

                        <Button
                            type="submit"
                            variant="contained"
                            disabled={
                                processing ||
                                (method === "passkey" && !passkeyRegistered)
                            }
                        >
                            {t('claim.show.submit')}
                        </Button>
                    </Stack>
                </Box>
            )}
        </AuthLayout>
    );
}
