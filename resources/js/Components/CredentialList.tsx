import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import Icon from './Icon';
import ListRow from './ListRow';
import type { RowActionItem } from './ActionDialog';
import { useActions } from '../lib/actions';
import { formatDateTime, formatRelative } from '../lib/datetime';
import { credentialIcon, credentialIconFamily, credentialLabel } from '../lib/credentials';
import { t } from '../lib/i18n';
import type { CredentialSummary } from '../types';
import RowDivider from './RowDivider';

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

    if (lastUsed !== null) parts.push(t('credential.list.last_used', { time: lastUsed }));
    else if (added !== null) parts.push(t('credential.list.added_unused', { time: added }));

    if (lastUsed !== null && added !== null) parts.push(t('credential.list.added', { time: added }));

    return parts.join(' ・ ');
}

interface CredentialListProps {
    credentials: CredentialSummary[];
    /** 渡すと、行を押したときに削除を選べる。読むだけの画面では省く */
    onRemove?: (credential: CredentialSummary) => void;
    /** 渡すと、名前を持てる認証手段 (パスキー) でだけ改名を選べる */
    onRename?: (credential: CredentialSummary) => void;
}

/**
 * 登録済みのログイン方法の一覧。
 *
 * 削除は種別ではなく行を指す。種別で消すと、同じ種別のものが一度に全部消える。
 */
export default function CredentialList({ credentials, onRemove, onRename }: CredentialListProps) {
    const { open, dialog } = useActions();

    const actionsFor = (credential: CredentialSummary): RowActionItem[] => [
        ...(onRename !== undefined && credential.type === 'passkey'
            ? [{ label: t('credential.action.rename'), onClick: () => onRename(credential) }]
            : []),
        ...(onRemove !== undefined
            ? [{ label: t('credential.action.remove'), destructive: true, onClick: () => onRemove(credential) }]
            : []),
    ];

    const select = (credential: CredentialSummary): (() => void) | undefined => {
        const actions = actionsFor(credential);
        if (actions.length === 0) return undefined;

        return () => open({ title: credentialLabel(credential), detail: detailOf(credential), actions });
    };

    return (
        <Paper variant="outlined">
            <Stack divider={<RowDivider />}>
                {credentials.length === 0 && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.9375rem', color: 'text.disabled' }}>
                        {t('credential.list.empty')}
                    </Typography>
                )}

                {credentials.map((credential) => (
                    <ListRow key={credential.id} onClick={select(credential)}>
                        <Box>
                            <Typography sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.9375rem' }}>
                                <Icon
                                    name={credentialIcon(credential)}
                                    family={credentialIconFamily(credential)}
                                    sx={{ width: 18, textAlign: 'center', color: 'text.disabled' }}
                                />
                                {credentialLabel(credential)}
                            </Typography>
                            <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                {detailOf(credential)}
                            </Typography>
                        </Box>
                    </ListRow>
                ))}
            </Stack>
            {dialog}
        </Paper>
    );
}
