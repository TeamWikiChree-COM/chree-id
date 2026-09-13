import { router, usePage } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import AppLayout from "../Components/AppLayout";
import CredentialList from "../Components/CredentialList";
import MergeCandidateList from "../Components/Dashboard/MergeCandidateList";
import type { MergeCandidate } from "../Components/Dashboard/MergeCandidateList";
import ProfileSummary from "../Components/Dashboard/ProfileSummary";
import ServiceList from "../Components/Dashboard/ServiceList";
import Icon from "../Components/Icon";
import SectionTitle from "../Components/SectionTitle";
import { useConfirm } from "../lib/confirm";
import { t } from "../lib/i18n";
import type { Account, ConnectedService, CredentialSummary } from "../types";

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
            crumbs={[{ label: t("dashboard.crumb") }]}
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

            <ProfileSummary account={account} />

            <SectionTitle note={t("settings.security.credentials.count", { count: credentials.length })}>
                {t("credential.heading")}
            </SectionTitle>
            <CredentialList credentials={credentials} />

            <MergeCandidateList candidates={mergeCandidates} />

            <SectionTitle note={t("common.count", { count: services.length })}>
                {t("dashboard.services.heading")}
            </SectionTitle>
            <ServiceList services={services} onRevoke={revoke} />

            <Stack direction="row" spacing={1} sx={{ mt: 3 }}>
                <Button
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
