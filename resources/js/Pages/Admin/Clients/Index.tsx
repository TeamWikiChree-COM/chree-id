import { router } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import ServiceIcon from "../../../Components/ServiceIcon";
import AppLayout from "../../../Components/AppLayout";
import Icon from "../../../Components/Icon";
import SectionTitle from "../../../Components/SectionTitle";
import type { OAuthClient, TrustValue } from "../../../types";

/** 信頼状態の表示。値そのものを出すと何が起きるか分からないので言い換える */
const TRUST_LABELS: Record<TrustValue, string> = {
    official: "公式",
    approved: "承認済み",
    unapproved: "未承認",
    disabled: "停止中",
};

interface IssuedSecret {
    clientId: string;
    /** public クライアントでは null */
    secret: string | null;
}

interface IndexProps {
    clients: OAuthClient[];
    /** 登録・再発行の直後だけ入る */
    issued: IssuedSecret | null;
}

export default function Index({ clients, issued }: IndexProps) {
    return (
        <AppLayout
            title="接続サービス"
            lead="ChreeID でログインできるサービスを管理します"
            crumbs={[
                { label: "ChreeID", href: "/" },
                { label: "システム管理", href: "/admin" },
                { label: "接続サービス" },
            ]}
        >
            {issued?.secret && (
                <Alert severity="warning" sx={{ mb: 2 }}>
                    <Typography sx={{ fontSize: "0.875rem", mb: 0.5 }}>
                        この画面を離れると client_secret は二度と表示されません
                    </Typography>
                    <Box
                        component="pre"
                        sx={{
                            m: 0,
                            fontSize: "0.8125rem",
                            whiteSpace: "pre-wrap",
                            wordBreak: "break-all",
                        }}
                    >
                        {`client_id     : ${issued.clientId}
client_secret : ${issued.secret}`}
                    </Box>
                </Alert>
            )}

            <Button
                variant="contained"
                startIcon={<Icon name="plus" />}
                onClick={() => router.get("/admin/clients/create")}
            >
                サービスを登録
            </Button>

            <SectionTitle note={`${clients.length}件`}>登録済み</SectionTitle>
            <Paper variant="outlined">
                <Stack
                    divider={
                        <Box
                            sx={{
                                borderBottom: "1px solid",
                                borderColor: "divider",
                            }}
                        />
                    }
                >
                    {clients.length === 0 && (
                        <Typography
                            sx={{
                                px: 2,
                                py: 1.5,
                                fontSize: "0.9375rem",
                                color: "text.disabled",
                            }}
                        >
                            登録されていません
                        </Typography>
                    )}

                    {clients.map((client) => (
                        <Box
                            key={client.id}
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "space-between",
                                gap: 2,
                                px: 2,
                                py: 1.5,
                            }}
                        >
                            <Box
                                sx={{
                                    display: "flex",
                                    alignItems: "center",
                                    gap: 1.5,
                                    minWidth: 0,
                                }}
                            >
                                <ServiceIcon
                                    name={client.name}
                                    iconUrl={client.iconUrl}
                                />
                                <Box sx={{ minWidth: 0 }}>
                                    <Typography
                                        sx={{
                                            display: "flex",
                                            alignItems: "center",
                                            gap: 1,
                                            fontSize: "0.9375rem",
                                        }}
                                    >
                                        {client.name}
                                        <Chip
                                            size="small"
                                            label={TRUST_LABELS[client.trust]}
                                        />
                                        {!client.isConfidential && (
                                            <Chip size="small" label="public" />
                                        )}
                                    </Typography>
                                    <Typography
                                        component="code"
                                        sx={{
                                            display: "block",
                                            fontSize: "0.8125rem",
                                            color: "text.disabled",
                                        }}
                                    >
                                        {client.id}
                                    </Typography>
                                    <Typography
                                        sx={{
                                            fontSize: "0.8125rem",
                                            color: "text.disabled",
                                            wordBreak: "break-all",
                                        }}
                                    >
                                        {client.redirectUris.join(" / ")}
                                    </Typography>
                                    {client.serviceAccounts > 0 && (
                                        <Typography
                                            sx={{
                                                fontSize: "0.8125rem",
                                                color: "text.disabled",
                                            }}
                                        >
                                            アカウント {client.serviceAccounts}
                                            件 ・ うち移行済み{" "}
                                            {client.migratedAccounts}件
                                        </Typography>
                                    )}
                                </Box>
                            </Box>
                            <Button
                                size="small"
                                color="inherit"
                                onClick={() =>
                                    router.get(
                                        `/admin/clients/${client.id}/edit`,
                                    )
                                }
                            >
                                編集
                            </Button>
                        </Box>
                    ))}
                </Stack>
            </Paper>
        </AppLayout>
    );
}
