import { Head } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Container from "@mui/material/Container";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemText from "@mui/material/ListItemText";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import ServiceIcon from "../../Components/ServiceIcon";

/** OIDC のスコープ名を画面表示用の日本語にする */
const SCOPE_LABELS: Record<string, string> = {
    openid: "アカウントの識別子",
    profile: "表示名",
    email: "メールアドレス",
};

interface ConsentProps {
    /** Registry に登録されたサービス名 */
    clientName: string;
    /** アイコンの URL。未設定なら null */
    clientIconUrl: string | null;
    /** 要求されたスコープ */
    scopes: string[];
    /** 承認 POST にそのまま引き継ぐ認可リクエストのパラメータ */
    query: Record<string, string>;
}

export default function Consent({
    clientName,
    clientIconUrl,
    scopes,
    query,
}: ConsentProps) {
    return (
        <>
            <Head title="連携の確認" />

            <Container maxWidth="xs" sx={{ py: 8 }}>
                <Stack spacing={3}>
                    <Box
                        sx={{ display: "flex", alignItems: "center", gap: 1.5 }}
                    >
                        <ServiceIcon
                            name={clientName}
                            iconUrl={clientIconUrl}
                            size={40}
                        />
                        <Box>
                            <Typography variant="h6" component="h1">
                                {clientName} との連携
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                                このサービスに以下の情報を渡します
                            </Typography>
                        </Box>
                    </Box>

                    <Paper sx={{ border: "1px solid", borderColor: "divider" }}>
                        <List dense disablePadding>
                            {scopes.map((scope) => (
                                <ListItem key={scope} divider>
                                    <ListItemText
                                        primary={SCOPE_LABELS[scope] ?? scope}
                                    />
                                </ListItem>
                            ))}
                        </List>
                    </Paper>

                    <Box
                        component="form"
                        method="post"
                        action="/oauth/authorize/approve"
                    >
                        {Object.entries(query).map(([key, value]) => (
                            <input
                                key={key}
                                type="hidden"
                                name={key}
                                value={value}
                            />
                        ))}
                        <Stack spacing={1}>
                            <Button type="submit" variant="contained">
                                許可する
                            </Button>
                            <Button href="/" color="inherit">
                                やめる
                            </Button>
                        </Stack>
                    </Box>
                </Stack>
            </Container>
        </>
    );
}
