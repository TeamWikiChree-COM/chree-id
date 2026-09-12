import { router } from "@inertiajs/react";
import ButtonBase from "@mui/material/ButtonBase";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import AuthLayout from "../../Components/AuthLayout";
import type { SignedInAccount } from "./MergePanel";
import { t } from "../../lib/i18n";

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
        <AuthLayout title={t('claim.choose.title')} heading={t('claim.choose.title')}>
            <Typography variant="body2" color="text.secondary">
                {t('claim.choose.description', { serviceName })}
            </Typography>

            <Stack spacing={2}>
                {card(
                    t('claim.choose.has_account.title'),
                    hasChreeId
                        ? t('claim.choose.has_account.description_named', { name: signedInAs.displayName ?? signedInAs.email ?? t('claim.choose.has_account.your_account') })
                        : t('claim.choose.has_account.description'),
                    "merge",
                )}

                {card(
                    t('claim.choose.first_time.title'),
                    t('claim.choose.first_time.description'),
                    "create",
                )}
            </Stack>
        </AuthLayout>
    );
}
