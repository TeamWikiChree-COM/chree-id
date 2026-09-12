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
import { t } from "../../../lib/i18n";
import type { OAuthClient, TrustValue } from "../../../types";

/** 信頼状態の表示。値そのものを出すと何が起きるか分からないので言い換える */
const TRUST_LABELS: Record<TrustValue, string> = {
    official: t('admin.clients.trust.official'),
    approved: t('admin.clients.trust.approved'),
    unapproved: t('admin.clients.trust.unapproved'),
    disabled: t('admin.clients.trust.disabled'),
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
            title={t('admin.clients.title')}
            lead={t('admin.clients.lead')}
            crumbs={[
                { label: "ChreeID", href: "/" },
                { label: t('admin.crumb'), href: "/admin" },
                { label: t('admin.clients.crumb') },
            ]}
        >
            {issued?.secret && (
                <Alert severity="warning" sx={{ mb: 2 }}>
                    <Typography sx={{ fontSize: "0.875rem", mb: 0.5 }}>
                        {t('admin.clients.issued.warning')}
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
                {t('admin.clients.register')}
            </Button>

            <SectionTitle note={t('admin.clients.count', { count: clients.length })}>{t('admin.clients.list.heading')}</SectionTitle>
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
                            {t('admin.clients.list.empty')}
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
                                            <Chip size="small" label={t('admin.clients.list.public')} />
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
                                            {t('admin.clients.list.accounts_note', {
                                                total: client.serviceAccounts,
                                                migrated: client.migratedAccounts,
                                            })}
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
                                {t('admin.clients.list.edit')}
                            </Button>
                        </Box>
                    ))}
                </Stack>
            </Paper>
        </AppLayout>
    );
}
