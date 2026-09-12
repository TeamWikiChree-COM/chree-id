import { router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { useState } from "react";
import { formatDateTime } from "../../lib/datetime";
import AuthLayout from "../../Components/AuthLayout";
import ServiceIcon from "../../Components/ServiceIcon";
import { t } from "../../lib/i18n";

interface ChoosableAccount {
    /** サービスアカウントのID */
    id: string;
    /** サービス側での識別子。OIDC 経由でできたものは分からない */
    serviceUserId: string | null;
    /** そのサービスと繋がった日時 */
    connectedAt: string | null;
}

interface ChooseAccountProps {
    /** 入ろうとしているサービス名 */
    clientName: string;
    /** アイコンの URL。未設定なら null */
    clientIconUrl: string | null;
    /** 選べるサービスアカウント */
    accounts: ChoosableAccount[];
    /** 認可リクエストのパラメータ。そのまま送り返す */
    query: Record<string, string>;
}

/**
 * どのサービスアカウントとして入るかを選ぶ画面。
 *
 * 統合すると1人が同じサービスに複数のアカウントを持ちうる。
 * 黙ってどれかを選ぶと別のアカウントとしてログインさせてしまうので、必ず本人に選ばせる。
 */
export default function ChooseAccount({
    clientName,
    clientIconUrl,
    accounts,
    query,
}: ChooseAccountProps) {
    const [sending, setSending] = useState(false);

    // 認可のパラメータはそのまま送り返す。サーバ側で改めて検証される
    const choose = (id: string): void => {
        setSending(true);
        router.post("/oauth/authorize/approve", {
            ...query,
            service_account_id: id,
        });
    };

    return (
        <AuthLayout
            title={t("oauth.choose_account.title")}
            heading={t("oauth.choose_account.title")}
        >
            <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
                <ServiceIcon name={clientName} iconUrl={clientIconUrl} />
                <Typography variant="body2" color="text.secondary">
                    {t("oauth.choose_account.description", { clientName })}
                </Typography>
            </Stack>

            <Stack spacing={1}>
                {accounts.map((account) => {
                    // 日付として読めなければ行ごと出さない。「連携日: (空)」を出さないため
                    const connectedAt = formatDateTime(account.connectedAt);

                    return (
                        <Box
                            key={account.id}
                            sx={{
                                border: "1px solid",
                                borderColor: "divider",
                                borderRadius: 2,
                                p: 2,
                            }}
                        >
                            <Stack
                                direction="row"
                                spacing={2}
                                sx={{
                                    alignItems: "center",
                                    justifyContent: "space-between",
                                }}
                            >
                                <Box>
                                    <Typography>
                                        {account.serviceUserId ??
                                            t("oauth.choose_account.unnamed")}
                                    </Typography>
                                    {connectedAt !== null && (
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            {t(
                                                "oauth.choose_account.connected_since",
                                                { date: connectedAt },
                                            )}
                                        </Typography>
                                    )}
                                </Box>
                                <Button
                                    variant="contained"
                                    disabled={sending}
                                    onClick={() => choose(account.id)}
                                >
                                    {t("auth.choose_identity.continue")}
                                </Button>
                            </Stack>
                        </Box>
                    );
                })}
            </Stack>
        </AuthLayout>
    );
}
