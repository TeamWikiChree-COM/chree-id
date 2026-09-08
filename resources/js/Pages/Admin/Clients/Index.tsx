import { Head, router } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Container from '@mui/material/Container';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemText from '@mui/material/ListItemText';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { OAuthClient, TrustValue } from '../../../types';

/** 信頼状態の表示。値そのものを出すと何が起きるか分からないので言い換える */
const TRUST_LABELS: Record<TrustValue, string> = {
    official: '公式',
    approved: '承認済み',
    unapproved: '未承認',
    disabled: '停止中',
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
        <>
            <Head title="接続サービス" />

            <Container maxWidth="md" sx={{ py: 6 }}>
                <Stack spacing={3}>
                    <Box>
                        <Typography variant="h6" component="h1">
                            接続サービス
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            ChreeID でログインできるサービスを管理します
                        </Typography>
                    </Box>

                    {issued?.secret && (
                        <Alert severity="warning">
                            <Typography variant="body2" gutterBottom>
                                この画面を離れると client_secret は二度と表示されません
                            </Typography>
                            <Box component="pre" sx={{ m: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-all' }}>
                                {`client_id     : ${issued.clientId}\nclient_secret : ${issued.secret}`}
                            </Box>
                        </Alert>
                    )}

                    <Box>
                        <Button variant="contained" onClick={() => router.get('/admin/clients/create')}>
                            サービスを登録
                        </Button>
                    </Box>

                    <Paper sx={{ border: '1px solid', borderColor: 'divider' }}>
                        <List dense disablePadding>
                            {clients.length === 0 && (
                                <ListItem>
                                    <ListItemText primary="登録されていません" />
                                </ListItem>
                            )}

                            {clients.map((client) => (
                                <ListItem
                                    key={client.id}
                                    divider
                                    secondaryAction={
                                        <Button
                                            size="small"
                                            color="inherit"
                                            onClick={() => router.get(`/admin/clients/${client.id}/edit`)}
                                        >
                                            編集
                                        </Button>
                                    }
                                >
                                    <ListItemText
                                        primary={
                                            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                                                <span>{client.name}</span>
                                                <Chip size="small" label={TRUST_LABELS[client.trust]} />
                                                {!client.isConfidential && <Chip size="small" label="public" />}
                                            </Stack>
                                        }
                                        secondary={
                                            <Box component="span" sx={{ display: 'block' }}>
                                                <Box component="code">{client.id}</Box>
                                                <Box component="span" sx={{ display: 'block' }}>
                                                    {client.redirectUris.join(' / ')}
                                                </Box>
                                            </Box>
                                        }
                                    />
                                </ListItem>
                            ))}
                        </List>
                    </Paper>

                    <Box>
                        <Button color="inherit" onClick={() => router.get('/')}>
                            アカウントに戻る
                        </Button>
                    </Box>
                </Stack>
            </Container>
        </>
    );
}
