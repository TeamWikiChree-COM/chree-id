import { Head, router } from '@inertiajs/react';
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
import type { Account, CredentialSummary, CredentialTypeValue } from '../types';

const TYPE_LABELS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'パスワード',
    magic_link: 'メールでログイン',
    totp: '認証アプリ (TOTP)',
    passkey: 'パスキー',
    oauth: '外部アカウント',
};

interface DashboardProps {
    account: Account;
    credentials: CredentialSummary[];
}

export default function Dashboard({ account, credentials }: DashboardProps) {
    return (
        <>
            <Head title="アカウント" />

            <Container maxWidth="sm" sx={{ py: 6 }}>
                <Stack spacing={3}>
                    <Box>
                        <Typography variant="h6" component="h1">
                            {account.displayName ?? '表示名を設定していません'}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            {account.email ?? 'メールアドレス未設定'}
                        </Typography>
                    </Box>

                    <Paper sx={{ p: 2, border: '1px solid', borderColor: 'divider' }}>
                        <Stack spacing={1}>
                            <Box>
                                <Typography variant="body2" color="text.secondary">
                                    ChreeID
                                </Typography>
                                <Typography variant="body2" component="code">
                                    {account.id}
                                </Typography>
                            </Box>
                            <Box>
                                <Chip
                                    size="small"
                                    label={account.origin === 'user' ? 'ユーザーアカウント' : 'サービスアカウント'}
                                />
                            </Box>
                        </Stack>
                    </Paper>

                    <Box>
                        <Typography variant="subtitle2" gutterBottom>
                            ログイン方法
                        </Typography>

                        <Paper sx={{ border: '1px solid', borderColor: 'divider' }}>
                            <List dense disablePadding>
                                {credentials.length === 0 && (
                                    <ListItem>
                                        <ListItemText primary="登録されていません" />
                                    </ListItem>
                                )}
                                {credentials.map((credential, index) => (
                                    <ListItem key={`${credential.type}-${index}`} divider>
                                        <ListItemText
                                            primary={TYPE_LABELS[credential.type] ?? credential.type}
                                            secondary={credential.lastUsedAt ? `最終利用 ${credential.lastUsedAt}` : '未使用'}
                                        />
                                    </ListItem>
                                ))}
                            </List>
                        </Paper>
                    </Box>

                    <Stack direction="row" spacing={1}>
                        <Button variant="outlined" color="inherit" onClick={() => router.get('/profile')}>
                            プロフィールを編集
                        </Button>
                        <Button variant="outlined" color="inherit" onClick={() => router.get('/security')}>
                            ログイン方法を管理
                        </Button>
                        <Button color="inherit" onClick={() => router.post('/logout')}>
                            ログアウト
                        </Button>
                    </Stack>
                </Stack>
            </Container>
        </>
    );
}
