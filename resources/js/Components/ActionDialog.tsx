import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemText from '@mui/material/ListItemText';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';
import { t } from '../lib/i18n';

export interface RowActionItem {
    label: string;
    onClick: () => void;
    /** 取り消せない操作か。赤で出す */
    destructive?: boolean;
    /** 押すと何が起きるかの補足 */
    description?: string;
}

export interface ActionRequest {
    title: string;
    /** タイトルの下に出す補足 (ID や日時など) */
    detail?: ReactNode;
    actions: RowActionItem[];
}

interface ActionDialogProps {
    /** 出す内容。null なら閉じている */
    request: ActionRequest | null;
    onClose: () => void;
}

/**
 * 一覧の1行に対してできる操作を選ばせる。
 *
 * 行の右端にボタンを並べない。数が増えると押し間違えやすく、
 * 狭い画面では行の中身を押し潰すため。
 */
export default function ActionDialog({ request, onClose }: ActionDialogProps) {
    if (request === null) return null;

    // 先に閉じる。操作の先で確認ダイアログを開くことがあり、重なると前面が分からなくなる
    const run = (action: RowActionItem): void => {
        onClose();
        action.onClick();
    };

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="xs">
            <DialogTitle sx={{ fontSize: '1rem', fontWeight: 700, overflowWrap: 'anywhere' }}>{request.title}</DialogTitle>
            <DialogContent sx={{ px: 1 }}>
                {request.detail !== undefined && (
                    <Typography component="div" sx={{ px: 2, pb: 1, fontSize: '0.8125rem', color: 'text.secondary', overflowWrap: 'anywhere' }}>
                        {request.detail}
                    </Typography>
                )}
                <List disablePadding>
                    {request.actions.map((action) => (
                        <ListItemButton key={action.label} onClick={() => run(action)} sx={{ borderRadius: 1 }}>
                            <ListItemText
                                primary={action.label}
                                secondary={action.description}
                                slotProps={{ primary: { sx: { fontSize: '0.9375rem', color: action.destructive ? 'error.main' : 'text.primary' } } }}
                            />
                        </ListItemButton>
                    ))}
                </List>
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={onClose}>
                    {t('common.confirm.cancel')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
