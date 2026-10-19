import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemIcon from "@mui/material/ListItemIcon";
import ListItemText from "@mui/material/ListItemText";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import AuthLayout from "../../Components/AuthLayout";
import OutlinedBox from "../../Components/OutlinedBox";
import ServiceIcon from "../../Components/ServiceIcon";
import { t } from "../../lib/i18n";

/** OIDC のスコープ名を画面表示用の文言にする */
const SCOPE_LABELS: Record<string, string> = {
    openid: t('oauth.consent.scope.openid'),
    profile: t('oauth.consent.scope.profile'),
    email: t('oauth.consent.scope.email'),
};

interface ConsentProps {
    /** Registry に登録されたサービス名 */
    clientName: string;
    /** アイコンの URL。未設定なら null */
    clientIconUrl: string | null;
    /** 要求されたスコープ */
    scopes: string[];
    /** 許可したときの送り先。OIDC とプラグインからのサインインで違う */
    action: string;
    /** 承認 POST にそのまま引き継ぐ認可リクエストのパラメータ */
    query: Record<string, string>;
}

export default function Consent({
    clientName,
    clientIconUrl,
    scopes,
    action,
    query,
}: ConsentProps) {
    return (
        <AuthLayout
            title={t('oauth.consent.head_title')}
            heading={t('oauth.consent.description')}
        >
            <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
                <ServiceIcon
                    name={clientName}
                    iconUrl={clientIconUrl}
                    size={44}
                />
                <Typography variant="h6" component="h1" sx={{ overflowWrap: "anywhere" }}>
                    {clientName}
                </Typography>
            </Stack>

            <OutlinedBox sx={{ p: 0 }}>
                <List dense disablePadding>
                    {scopes.map((scope, index) => (
                        <ListItem key={scope} divider={index < scopes.length - 1}>
                            <ListItemIcon sx={{ minWidth: 40, color: "primary.main" }}>
                                <Box
                                    component="svg"
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    sx={{ width: 20, height: 20, fill: "none", stroke: "currentColor", strokeWidth: 2 }}
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 11v5m0-8h.01" />
                                </Box>
                            </ListItemIcon>
                            <ListItemText primary={SCOPE_LABELS[scope] ?? scope} />
                        </ListItem>
                    ))}
                </List>
            </OutlinedBox>

            <Box component="form" method="post" action={action}>
                {Object.entries(query).map(([key, value]) => (
                    <input key={key} type="hidden" name={key} value={value} />
                ))}
                <Stack spacing={1}>
                    <Button type="submit" variant="contained" size="large" fullWidth>
                        {t('oauth.consent.allow')}
                    </Button>
                    <Button href="/" color="inherit" size="large" fullWidth>
                        {t('oauth.consent.cancel')}
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
