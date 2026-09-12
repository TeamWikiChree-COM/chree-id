import { router, usePage } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import ServiceIcon from "../Components/ServiceIcon";
import AppLayout from "../Components/AppLayout";
import RowAction from "../Components/RowAction";
import CredentialList from "../Components/CredentialList";
import Icon from "../Components/Icon";
import SectionTitle from "../Components/SectionTitle";
import { useConfirm } from "../lib/confirm";
import { formatDateTime } from "../lib/datetime";
import type { Account, ConnectedService, CredentialSummary } from "../types";

/** 同じアドレスの別アカウント。挙げるだけで、勝手には統合しない */
interface MergeCandidate {
    id: string;
    displayName: string | null;
    email: string | null;
    hasUserAccount: boolean;
}

interface DashboardProps {
    account: Account;
    credentials: CredentialSummary[];
    services: ConnectedService[];
    mergeCandidates: MergeCandidate[];
}

export default function Dashboard({
    account,
    credentials,
    services,
    mergeCandidates,
}: DashboardProps) {
    const { flash } = usePage().props;

    const { ask, dialog } = useConfirm();

    const revoke = (service: ConnectedService): void => {
        ask({
            title: `${service.name} との連携を解除しますか`,
            description:
                "発行済みのアクセストークンが無効になり、次に使うときは改めてログインが必要です",
            confirmText: "解除する",
            onConfirm: () => router.post(`/services/${service.clientId}/revoke`),
        });
    };

    return (
        <AppLayout
            title="アカウント"
            lead="連携先のサービスに渡される情報と、ログインに使える手段です"
            crumbs={[{ label: "ChreeID", href: "/" }, { label: "アカウント" }]}
        >
            {flash.serviceRevoked && (
                <Alert severity="success" sx={{ mb: 2 }}>
                    連携を解除しました
                </Alert>
            )}

            <Paper variant="outlined" sx={{ p: 2 }}>
                <Box
                    sx={{
                        display: "flex",
                        alignItems: "flex-start",
                        justifyContent: "space-between",
                        gap: 2,
                    }}
                >
                    <Box>
                        <Typography
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                gap: 1,
                                fontSize: "0.9375rem",
                            }}
                        >
                            {account.displayName ?? "表示名を設定していません"}
                            <Chip
                                size="small"
                                label={
                                    account.origin === "user"
                                        ? "ユーザー"
                                        : "サービス"
                                }
                            />
                        </Typography>

                        <Typography
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                gap: 1,
                                fontSize: "0.8125rem",
                                color: "text.disabled",
                            }}
                        >
                            {account.email ?? "メールアドレス未設定"}
                            {account.email !== null && (
                                <Chip
                                    size="small"
                                    label={
                                        account.emailVerified
                                            ? "確認済み"
                                            : "未確認"
                                    }
                                    color={
                                        account.emailVerified
                                            ? "success"
                                            : "default"
                                    }
                                />
                            )}
                        </Typography>

                        <Typography
                            component="code"
                            sx={{
                                fontSize: "0.8125rem",
                                color: "text.disabled",
                            }}
                        >
                            {account.id}
                        </Typography>
                    </Box>

                    <Button
                        size="small"
                        color="inherit"
                        onClick={() => router.get("/settings")}
                    >
                        編集
                    </Button>
                </Box>
            </Paper>

            <SectionTitle note={`${credentials.length}件`}>
                ログイン方法
            </SectionTitle>
            <CredentialList credentials={credentials} />

            {mergeCandidates.length > 0 && (
                <>
                    <SectionTitle note={`${mergeCandidates.length}件`}>
                        同じメールアドレスの別アカウント
                    </SectionTitle>
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
                            {mergeCandidates.map((candidate) => (
                                <Box key={candidate.id} sx={{ px: 2, py: 1.5 }}>
                                    <Typography sx={{ fontSize: "0.9375rem" }}>
                                        {candidate.displayName ??
                                            candidate.email ??
                                            candidate.id}
                                    </Typography>
                                    <Typography
                                        sx={{
                                            fontSize: "0.8125rem",
                                            color: "text.disabled",
                                        }}
                                    >
                                        {candidate.hasUserAccount
                                            ? "まとめる人格を持っています"
                                            : "サービスから作られたアカウントです"}
                                    </Typography>
                                </Box>
                            ))}
                        </Stack>
                    </Paper>
                    <Typography
                        sx={{
                            mt: 1,
                            fontSize: "0.8125rem",
                            color: "text.disabled",
                        }}
                    >
                        同じアドレスを使っているだけの別のアカウントかもしれません。
                        まとめるかどうかはご自身で決められます。アドレスが同じというだけで、
                        こちらが勝手にまとめることはありません。
                    </Typography>
                </>
            )}

            <SectionTitle note={`${services.length}件`}>
                連携しているサービス
            </SectionTitle>
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
                    {services.length === 0 && (
                        <Typography
                            sx={{
                                px: 2,
                                py: 1.5,
                                fontSize: "0.9375rem",
                                color: "text.disabled",
                            }}
                        >
                            まだどのサービスとも連携していません
                        </Typography>
                    )}

                    {services.map((service) => (
                        <Box
                            key={service.id}
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
                                    name={service.name}
                                    iconUrl={service.iconUrl}
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
                                        {service.name}
                                        {service.trust === "official" && (
                                            <Chip size="small" label="公式" />
                                        )}
                                    </Typography>
                                    <Typography
                                        sx={{
                                            fontSize: "0.8125rem",
                                            color: "text.disabled",
                                        }}
                                    >
                                        {service.serviceUserId ??
                                            (formatDateTime(service.connectedAt)
                                                ? `${String(formatDateTime(service.connectedAt))} に連携`
                                                : "連携済み")}
                                        {!service.hasActiveToken &&
                                            " ・ 現在ログインしていません"}
                                    </Typography>
                                </Box>
                            </Box>
                            <Stack direction="row" spacing={1}>
                                <RowAction
                                    onClick={() =>
                                        router.get(
                                            `/services/${service.id}/split`,
                                        )
                                    }
                                >
                                    外す
                                </RowAction>
                                <RowAction
                                    destructive
                                    onClick={() => revoke(service)}
                                >
                                    解除
                                </RowAction>
                            </Stack>
                        </Box>
                    ))}
                </Stack>
            </Paper>

            <Stack direction="row" spacing={1} sx={{ mt: 3 }}>
                <Button
                    variant="outlined"
                    color="inherit"
                    startIcon={<Icon name="gear" />}
                    onClick={() => router.get("/settings")}
                >
                    設定
                </Button>
                <Button
                    color="inherit"
                    startIcon={<Icon name="arrow-right-from-bracket" />}
                    onClick={() => router.post("/logout")}
                >
                    ログアウト
                </Button>
            </Stack>

            {dialog}
        </AppLayout>
    );
}
