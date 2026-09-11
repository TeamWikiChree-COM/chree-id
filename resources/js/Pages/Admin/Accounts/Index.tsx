import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '../../../Components/AppLayout';
import Icon from '../../../Components/Icon';
import SectionTitle from '../../../Components/SectionTitle';

interface AdminAccount {
    id: string;
    email: string | null;
    displayName: string | null;
    origin: 'user' | 'service';
    isEmailVerified: boolean;
    isSuspended: boolean;
    isAdmin: boolean;
    createdAt: string;
    credentialTypes: string[];
}

interface IndexProps {
    accounts: AdminAccount[];
}

/** 認証方式の表示ラベル */
const CREDENTIAL_LABELS: Record<string, string> = {
    password: 'パスワード',
    passkey: 'パスキー',
    totp: '2FA',
    magic_link: 'マジックリンク',
};

/**
 * システム管理のアカウント一覧。
 *
 * 登録済みアカウントの利用状態を運営者向けに一覧表示する。
 */
export default function Index({ accounts }: IndexProps) {
    return (
        <AppLayout
            title="アカウント"
            lead="登録されている ChreeID アカウントを一覧で確認します"
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: 'システム管理', href: '/admin' },
                { label: 'アカウント' },
            ]}
        >
            <SectionTitle note={`${accounts.length}件`}>アカウント一覧</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {accounts.length === 0 && (
                        <Typography sx={{ px: 2, py: 2, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            アカウントが登録されていません
                        </Typography>
                    )}

                    {accounts.map((account) => (
                        <Box
                            key={account.id}
                            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}
                        >
                            <Box sx={{ minWidth: 0 }}>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexWrap: 'wrap', mb: 0.5 }}>
                                    <Icon
                                        name={account.origin === 'service' ? 'robot' : 'user'}
                                        sx={{ color: 'text.secondary', fontSize: '0.9375rem' }}
                                    />
                                    <Typography sx={{ fontWeight: 600, fontSize: '0.9375rem' }}>
                                        {account.displayName || '(未設定)'}
                                    </Typography>
                                    <Chip
                                        size="small"
                                        variant="outlined"
                                        label={account.origin === 'service' ? 'サービスアカウント' : 'ユーザーアカウント'}
                                    />
                                    {account.isAdmin && <Chip size="small" color="primary" label="管理者" />}
                                    {account.isSuspended && <Chip size="small" color="error" label="停止中" />}
                                    {account.email && (
                                        <Chip
                                            size="small"
                                            color={account.isEmailVerified ? 'success' : 'default'}
                                            label={account.isEmailVerified ? '確認済み' : '未確認'}
                                        />
                                    )}
                                </Box>

                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', mb: 0.25 }}>
                                    {account.email || '(メールアドレス未登録)'}
                                </Typography>

                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, flexWrap: 'wrap' }}>
                                    <Typography component="code" sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                        {account.id}
                                    </Typography>

                                    {account.credentialTypes.length > 0 && (
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                            認証: {account.credentialTypes.map((t) => CREDENTIAL_LABELS[t] ?? t).join(', ')}
                                        </Typography>
                                    )}
                                </Box>
                            </Box>

                            <Box sx={{ textAlign: 'right', flexShrink: 0 }}>
                                <Typography sx={{ fontSize: '0.8125rem', color: 'text.secondary' }}>
                                    {account.createdAt}
                                </Typography>
                            </Box>
                        </Box>
                    ))}
                </Stack>
            </Paper>
        </AppLayout>
    );
}
