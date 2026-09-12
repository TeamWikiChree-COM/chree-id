import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import Icon from '../Icon';
import RowAction from '../RowAction';
import type { CredentialSummary, CredentialTypeValue } from '../../types';

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

interface CredentialListProps {
    credentials: CredentialSummary[];
}

/**
 * 登録済みのログイン方法の一覧。
 *
 * 削除は種別ではなく行を指す。パスキーや外部アカウントは同じ種別を複数持てるので、
 * 種別で消すと一度に全部消える。
 */
export default function CredentialList({ credentials }: CredentialListProps) {
    return (
        <Paper variant="outlined">
            <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                {credentials.map((credential) => (
                    <Box
                        key={credential.id}
                        sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}
                    >
                        <Box>
                            <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                <Icon
                                    name={TYPE_ICONS[credential.type] ?? 'circle-question'}
                                    sx={{ width: 18, textAlign: 'center', color: 'text.disabled' }}
                                />
                                {credential.label ?? TYPE_LABELS[credential.type] ?? credential.type}
                            </Typography>
                            <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                {credential.lastUsedAt ? `最終利用 ${credential.lastUsedAt}` : '未使用'}
                            </Typography>
                        </Box>
                        <RowAction
                            destructive
                            onClick={() => router.post('/security/credentials/remove', { id: credential.id })}
                        >
                            削除
                        </RowAction>
                    </Box>
                ))}
            </Stack>
        </Paper>
    );
}
