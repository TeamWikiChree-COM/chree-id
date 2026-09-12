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
import { t } from "../lib/i18n";
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
            title: t("dashboard.services.revoke_confirm.title", { name: service.name }),
            description: t("dashboard.services.revoke_confirm.description"),
            confirmText: t("dashboard.services.revoke_confirm.confirm"),
            onConfirm: () => router.post(`/services/${service.clientId}/revoke`),
        });
    };

    return (
        <AppLayout
            title={t("dashboard.title")}
            lead={t("dashboard.lead")}
            crumbs={[{ label: "ChreeID", href: "/" }, { label: t("dashboard.crumb") }]}
        >
            {flash.accountMerged && (
                <Alert severity="success" sx={{ mb: 2 }}>
                    {t("dashboard.merged")}
                </Alert>
            )}

            {flash.serviceRevoked && (
                <Alert severity="success" sx={{ mb: 2 }}>
                    {t("dashboard.service_revoked")}
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
                            {account.displayName ?? t("dashboard.profile.no_display_name")}
                            <Chip
                                size="small"
                                label={
                                    account.origin === "user"
                                        ? t("dashboard.profile.origin_user")
                                        : t("dashboard.profile.origin_service")
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
                            {account.email ?? t("dashboard.profile.no_email")}
                            {account.email !== null && (
                                <Chip
                                    size="small"
                                    label={
                                        account.emailVerified
                                            ? t("dashboard.profile.email_verified")
                                            : t("dashboard.profile.email_unverified")
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
                        {t("common.action.edit")}
                    </Button>
                </Box>
            </Paper>

            <SectionTitle note={t("settings.security.credentials.count", { count: credentials.length })}>
                {t("credential.heading")}
            </SectionTitle>
            <CredentialList credentials={credentials} />

            {mergeCandidates.length > 0 && (
                <>
                    <SectionTitle note={t("common.count", { count: mergeCandidates.length })}>
                        {t("dashboard.merge.heading")}
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
                                <Box
                                    key={candidate.id}
                                    sx={{
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "space-between",
                                        gap: 2,
                                        px: 2,
                                        py: 1.5,
                                    }}
                                >
                                    <Box>
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
                                            ? t("dashboard.merge.has_account")
                                            : t("dashboard.merge.service_created")}
                                    </Typography>
                                    </Box>

                                    {/* 提示と実行は別処理。ここは相手側を証明する画面へ送るだけ */}
                                    <RowAction
                                        onClick={() =>
                                            router.get(`/settings/merge/${candidate.id}`)
                                        }
                                    >
                                        {t("dashboard.merge.action")}
                                    </RowAction>
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
                        {t("dashboard.merge.note")}
                    </Typography>
                </>
            )}

            <SectionTitle note={t("common.count", { count: services.length })}>
                {t("dashboard.services.heading")}
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
                            {t("dashboard.services.empty")}
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
                                            <Chip size="small" label={t("dashboard.services.official")} />
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
                                                ? t("dashboard.services.connected_at", { time: String(formatDateTime(service.connectedAt)) })
                                                : t("dashboard.services.connected"))}
                                        {!service.hasActiveToken &&
                                            ` ・ ${t("dashboard.services.inactive")}`}
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
                                    {t("dashboard.services.split")}
                                </RowAction>
                                <RowAction
                                    destructive
                                    onClick={() => revoke(service)}
                                >
                                    {t("dashboard.services.revoke")}
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
                    {t("common.nav.settings")}
                </Button>
                <Button
                    color="inherit"
                    startIcon={<Icon name="arrow-right-from-bracket" />}
                    onClick={() => router.post("/logout")}
                >
                    {t("common.nav.logout")}
                </Button>
            </Stack>

            {dialog}
        </AppLayout>
    );
}
