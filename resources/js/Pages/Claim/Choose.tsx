import { router } from "@inertiajs/react";
import ButtonBase from "@mui/material/ButtonBase";
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
export default function ClaimChoose({
    token,
    serviceName,
    signedInAs,
}: ClaimChooseProps) {
    const go = (path: string): void => router.get(`/claim/${token}/${path}`);

    // ボックスごと押せるようにする。中のボタンだけが的だと的が小さい
    const card = (
        title: string,
        description: string,
        path: string,
    ) => (
        <ButtonBase
            onClick={() => go(path)}
            sx={{
                display: "block",
                width: "100%",
                textAlign: "left",
                border: "1px solid",
                borderColor: "divider",
                borderRadius: 2,
                p: 2,
                "&:hover": {
                    borderColor: "primary.main",
                    bgcolor: "action.hover",
                },
            }}
        >
            <Stack spacing={0.5}>
                <Typography sx={{ fontWeight: 600 }}>{title}</Typography>
                <Typography variant="body2" color="text.secondary">
                    {description}
                </Typography>
            </Stack>
        </ButtonBase>
    );

    const hasChreeId = signedInAs !== null;

    return (
        <AuthLayout title="ChreeID を用意する" heading="ChreeID を用意する">
            <Typography variant="body2" color="text.secondary">
                {serviceName}
                でお使いのアカウントを ChreeID として使えるようにします。
                これまでの利用状況はそのまま引き継がれます。
            </Typography>

            <Stack spacing={2}>
                {card(
                    "既に ChreeID を持っている",
                    hasChreeId
                        ? `${signedInAs.displayName ?? signedInAs.email ?? "お使いのアカウント"} に追加します。`
                        : "お使いの ChreeID にログインし、このアカウントをそこに統合します。",
                    "merge",
                )}

                {card(
                    "はじめて利用する",
                    "新しく ChreeID を作ります。ログイン方法をこのあと決めます。",
                    "create",
                )}
            </Stack>
        </AuthLayout>
    );
}
