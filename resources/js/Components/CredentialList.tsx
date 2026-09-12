import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import Icon from './Icon';
import RowAction from './RowAction';
import { formatDateTime, formatRelative } from '../lib/datetime';
import { idpIcon, idpIconFamily, idpLabel } from '../lib/idps';
import type { CredentialSummary, CredentialTypeValue } from '../types';

const TYPE_LABELS: Partial<Record<CredentialTypeValue, string>> = {
    password: 'パスワード',
    magic_link: 'メールのリンク',
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

/**
 * 行の名前。
 *
 * 外部アカウントとパスキーは複数持てるので、種別名だけだと同じ行が並ぶ。
 * 連携先や端末名が分かるならそちらを名前にする。
 *
 * @param credential 認証手段の1行
 */
function titleOf(credential: CredentialSummary): string {
    if (credential.provider !== null) return idpLabel(credential.provider);
    if (credential.label !== null) return credential.label;

    return TYPE_LABELS[credential.type] ?? credential.type;
}

/**
 * 行の下に添える手がかり。
 *
 * 「未使用」だけを出さない。一度も使っていない認証手段でも、いつ登録したかは分かる。
 *
 * @param credential 認証手段の1行
 */
function detailOf(credential: CredentialSummary): string {
    const parts: string[] = [];

    if (credential.detail !== null) parts.push(credential.detail);

    const lastUsed = formatRelative(credential.lastUsedAt);
    const added = formatDateTime(credential.createdAt);

    if (lastUsed !== null) parts.push(`最終利用 ${lastUsed}`);
    else if (added !== null) parts.push(`${added} に登録 (未使用)`);

    if (lastUsed !== null && added !== null) parts.push(`${added} に登録`);

    return parts.join(' ・ ');
}

interface CredentialListProps {
    credentials: CredentialSummary[];
    /** 渡すと削除ボタンを出す。読むだけの画面では省く */
    onRemove?: (credential: CredentialSummary) => void;
    /** 渡すと、名前を持てる認証手段 (パスキー) にだけ改名ボタンを出す */
    onRename?: (credential: CredentialSummary) => void;
}

/**
 * 登録済みのログイン方法の一覧。
 *
 * 削除は種別ではなく行を指す。種別で消すと、同じ種別のものが一度に全部消える。
 */
export default function CredentialList({ credentials, onRemove, onRename }: CredentialListProps) {
    return (
        <Paper variant="outlined">
            <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                {credentials.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.9375rem', color: 'text.disabled' }}>
                        登録されていません
                    </Typography>
                )}

                {credentials.map((credential) => (
                    <Box
                        key={credential.id}
                        sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}
                    >
                        <Box>
                            <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                <Icon
                                    name={
                                        credential.provider === null
                                            ? TYPE_ICONS[credential.type] ?? 'circle-question'
                                            : idpIcon(credential.provider)
                                    }
                                    family={
                                        credential.provider === null
                                            ? 'solid'
                                            : idpIconFamily(credential.provider)
                                    }
                                    sx={{ width: 18, textAlign: 'center', color: 'text.disabled' }}
                                />
                                {titleOf(credential)}
                            </Typography>
                            <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                {detailOf(credential)}
                            </Typography>
                        </Box>

                        <Stack direction="row" spacing={1} sx={{ flexShrink: 0 }}>
                            {onRename !== undefined && credential.type === 'passkey' && (
                                <RowAction onClick={() => onRename(credential)}>名前を変更</RowAction>
                            )}

                            {onRemove !== undefined && (
                                <RowAction destructive onClick={() => onRemove(credential)}>
                                    削除
                                </RowAction>
                            )}
                        </Stack>
                    </Box>
                ))}
            </Stack>
        </Paper>
    );
}
