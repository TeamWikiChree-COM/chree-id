import { useForm, usePage } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
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
}

type Method = "password" | "passkey" | "google";

/**
 * 引き取り (claim) の画面。
 *
 * 利用者から見れば新規作成だが、実際にはサービス利用時に裏で用意された
 * アカウントを自分のものにする操作。そこは説明せず、結果だけ伝える。
 * 認証手段はパスワードに限らず、パスキーや Google 連携でも引き取れる。
 */
export default function ClaimShow({
    token,
    serviceName,
    email,
    emailVerified,
    displayName,
    hasPassword,
}: ClaimShowProps) {
    const { externalIdps } = usePage().props;
    const googleAvailable = externalIdps.includes("google");

    const initialMethod: Method =
        hasPassword || !googleAvailable ? "password" : "google";
    const [method, setMethod] = useState<Method>(initialMethod);
    const [passkeyError, setPasskeyError] = useState<string | null>(null);
    const [passkeyRegistered, setPasskeyRegistered] = useState(false);
    const [passkeyBusy, setPasskeyBusy] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        token: string;
        method: Method;
        password: string;
        display_name: string;
        email: string;
    }>({
        token,
        method: initialMethod,
        password: "",
        display_name: displayName ?? "",
        email: email ?? "",
    });

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
                    : "パスキーを登録できませんでした",
            );
        } finally {
            setPasskeyBusy(false);
        }
    };

    const emailField =
        email !== null && emailVerified ? (
            <TextField
                label="メールアドレス"
                value={email}
                slotProps={{ input: { readOnly: true } }}
            />
        ) : (
            <TextField
                label="メールアドレス"
                type="email"
                value={data.email}
                onChange={(e) => setData("email", e.target.value)}
                error={Boolean(errors.email)}
                helperText={errors.email ?? "確認のメールをお送りします"}
                autoComplete="email"
                required
            />
        );

    const displayNameField = (
        <TextField
            label="表示名"
            value={data.display_name}
            onChange={(e) => setData("display_name", e.target.value)}
            error={Boolean(errors.display_name)}
            helperText={errors.display_name ?? "任意。あとから変更できます"}
            autoComplete="nickname"
        />
    );

    return (
        <AuthLayout title="ChreeID を作成" heading="ChreeID を作成">
            <Typography variant="body2" color="text.secondary">
                {serviceName}
                でお使いのアカウントを、ChreeID として使えるようにします。
                これまでの利用状況はそのまま引き継がれます。
            </Typography>

            {!hasPassword && (
                <Alert severity="info">
                    元のサービスではパスワードを使っていないようです。同じ方法で引き取れます
                </Alert>
            )}

            <Tabs
                value={method}
                onChange={(_, next: Method) => changeMethod(next)}
                variant="fullWidth"
            >
                <Tab value="password" label="パスワード" />
                <Tab value="passkey" label="パスキー" />
                {googleAvailable && <Tab value="google" label="Google" />}
            </Tabs>

            {method === "google" ? (
                <Stack spacing={2}>
                    {emailField}
                    <Typography variant="body2" color="text.secondary">
                        このアカウントのメールアドレスと同じ Google
                        アカウントで連携すると、そのまま引き取れます
                    </Typography>
                    <Button
                        component="a"
                        href={`/auth/google/redirect?claim_token=${encodeURIComponent(token)}`}
                        variant="contained"
                        startIcon={<Icon name="google" family="brands" />}
                    >
                        Google で引き取る
                    </Button>
                </Stack>
            ) : (
                <Box component="form" onSubmit={submit} noValidate>
                    <Stack spacing={2}>
                        {emailField}

                        {method === "password" && (
                            <PasswordField
                                label="パスワード"
                                value={data.password}
                                onChange={(e) =>
                                    setData("password", e.target.value)
                                }
                                error={Boolean(errors.password)}
                                helperText={errors.password ?? "8文字以上"}
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
                                        この端末にパスキーを登録しました
                                    </Alert>
                                ) : (
                                    <Button
                                        variant="outlined"
                                        color="inherit"
                                        startIcon={<Icon name="fingerprint" />}
                                        disabled={passkeyBusy}
                                        onClick={() => void addPasskey()}
                                    >
                                        この端末にパスキーを登録する
                                    </Button>
                                )}
                            </Stack>
                        )}

                        {displayNameField}

                        {errors.token && (
                            <Alert severity="error">{errors.token}</Alert>
                        )}

                        <Button
                            type="submit"
                            variant="contained"
                            disabled={
                                processing ||
                                (method === "passkey" && !passkeyRegistered)
                            }
                        >
                            ChreeID を作成する
                        </Button>
                    </Stack>
                </Box>
            )}
        </AuthLayout>
    );
}
