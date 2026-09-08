import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '../Components/AppLayout';
import Icon from '../Components/Icon';
import SectionTitle from '../Components/SectionTitle';
import type { Account, CredentialSummary, CredentialTypeValue } from '../types';

const TYPE_LABELS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'パスワード',
    magic_link: 'マジックリンク',
    totp: '認証アプリ (TOTP)',
    passkey: 'パスキー',
    oauth: '外部アカウント',
};

const TYPE_ICONS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'key',
    magic_link: 'envelope',
    totp: 'mobile-screen',
    passkey: 'fingerprint',
    oauth: 'right-to-bracket',
};

interface DashboardProps {
    account: Account;
    credentials: CredentialSummary[];
}

export default function Dashboard({ account, credentials }: DashboardProps) {
    return (
        <AppLayout
            title="アカウント"
            lead="連携先のサービスに渡される情報と、ログインに使える手段です"
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: 'アカウント' }]}
        >
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 2 }}>
                    <Box>
                        <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                            {account.displayName ?? '表示名を設定していません'}
                            <Chip
                                size="small"
                                label={account.origin === 'user' ? 'ユーザー' : 'サービス'}
                            />
                        </Typography>

                        <Typography
                            sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.8125rem', color: 'text.disabled' }}
                        >
                            {account.email ?? 'メールアドレス未設定'}
                            {account.email !== null && (
                                <Chip
                                    size="small"
                                    label={account.emailVerified ? '確認済み' : '未確認'}
                                    color={account.emailVerified ? 'success' : 'default'}
                                />
                            )}
                        </Typography>

                        <Typography component="code" sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                            {account.id}
                        </Typography>
                    </Box>

                    <Button size="small" color="inherit" onClick={() => router.get('/settings')}>
                        編集
                    </Button>
                </Box>
            </Paper>

            <SectionTitle note={`${credentials.length}件`}>ログイン方法</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {credentials.length === 0 && (
                        <Typography sx={{ px: 2, py: 1.5, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            登録されていません
                        </Typography>
                    )}

                    {credentials.map((credential, index) => (
                        <Box key={`${credential.type}-${index}`} sx={{ px: 2, py: 1.5 }}>
                            <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                <Icon
                                    name={TYPE_ICONS[credential.type] ?? 'circle-question'}
                                    sx={{ width: 18, textAlign: 'center', color: 'text.disabled' }}
                                />
                                {TYPE_LABELS[credential.type] ?? credential.type}
                            </Typography>
                            <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                {credential.lastUsedAt ? `最終利用 ${credential.lastUsedAt}` : '未使用'}
                            </Typography>
                        </Box>
                    ))}
                </Stack>
            </Paper>

            <Stack direction="row" spacing={1} sx={{ mt: 3 }}>
                <Button variant="outlined" color="inherit" startIcon={<Icon name="gear" />} onClick={() => router.get('/settings')}>
                    設定
                </Button>
                <Button color="inherit" startIcon={<Icon name="arrow-right-from-bracket" />} onClick={() => router.post('/logout')}>
                    ログアウト
                </Button>
            </Stack>
        </AppLayout>
    );
}
