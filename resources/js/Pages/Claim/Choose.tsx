import { router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import AuthLayout from "../../Components/AuthLayout";
import type { SignedInAccount } from "./MergePanel";

interface ClaimChooseProps {
    /** サービスから渡された平文トークン */
    token: string;
    /** 引き取り元のサービス名 */
    serviceName: string;
    /** ログイン中のアカウント。あれば統合のほうを勧める */
    signedInAs: SignedInAccount | null;
}

/**
 * 「はじめて使う」か「もう持っている」かだけを聞く画面。
 *
 * 認証手段の選択と混ぜると何を聞かれているのか分かりにくいので、先にここで分ける。
 */
export default function ClaimChoose({ token, serviceName, signedInAs }: ClaimChooseProps) {
    const go = (path: string): void => router.get(`/claim/${token}/${path}`);

    const card = (
        title: string,
        description: string,
        label: string,
        path: string,
        primary: boolean,
    ) => (
        <Box sx={{ border: "1px solid", borderColor: "divider", borderRadius: 2, p: 2 }}>
            <Stack spacing={1}>
                <Typography sx={{ fontWeight: 600 }}>{title}</Typography>
                <Typography variant="body2" color="text.secondary">
                    {description}
                </Typography>
                <Box>
                    <Button variant={primary ? "contained" : "outlined"} color={primary ? "primary" : "inherit"} onClick={() => go(path)}>
                        {label}
                    </Button>
                </Box>
            </Stack>
        </Box>
    );

    const hasChreeId = signedInAs !== null;

    return (
        <AuthLayout title="ChreeID を用意する" heading="ChreeID を用意する">
            <Typography variant="body2" color="text.secondary">
                {serviceName}
                でお使いのアカウントを、WikiChree.COM 共通の ChreeID として使えるようにします。
                これまでの利用状況はそのまま引き継がれます。
            </Typography>

            <Stack spacing={2}>
                {card(
                    "ChreeID を持っている",
                    hasChreeId
                        ? `${signedInAs.displayName ?? signedInAs.email ?? "お使いのアカウント"} に追加します。`
                        : "お使いの ChreeID にログインして、このアカウントをそこに追加します。",
                    "持っている ChreeID に追加する",
                    "merge",
                    hasChreeId,
                )}

                {card(
                    "はじめて使う",
                    "新しく ChreeID を作ります。ログイン方法をこのあと決めます。",
                    "新しく作る",
                    "create",
                    !hasChreeId,
                )}
            </Stack>
        </AuthLayout>
    );
}
