import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { t } from '../lib/i18n';

export interface ConfirmRequest {
    title: string;
    description: ReactNode;
    /** 実行ボタンの文言。省略すると「削除する」 */
    confirmText?: string;
    /**
     * 取り消せない操作か。
     *
     * 既定は true。このダイアログを出すこと自体が「戻せない」の合図なので、
     * 戻せる操作でわざわざ確認を挟むほうが例外的。
     */
    destructive?: boolean;
    /**
     * 入力させて一致を求める文字列。
     *
     * 巻き添えの大きい操作 (まとめて消す、他の端末を全部切る) にだけ付ける。
     * 全部に付けると読まずに写すだけの作業になり、確認の意味が薄れる。
     */
    expected?: string;
    onConfirm: () => void;
}

interface ConfirmDialogProps {
    /** 確認したい内容。null なら閉じている */
    request: ConfirmRequest | null;
    onClose: () => void;
}

/**
 * 取り消せない操作の確認。
 *
 * `window.confirm` を使わない。ブラウザ既定の見た目になるうえ、
 * 何をどう消すのかを整えて見せられない。
 */
export default function ConfirmDialog({ request, onClose }: ConfirmDialogProps) {
    const [typed, setTyped] = useState('');

    // 開くたびに空に戻す。前の入力が残っていると、読まずに押せてしまう
    useEffect(() => {
        if (request !== null) setTyped('');
    }, [request]);

    if (request === null) return null;

    const destructive = request.destructive ?? true;
    const matched = request.expected === undefined || typed === request.expected;

    const confirm = (): void => {
        request.onConfirm();
        onClose();
    };

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="xs">
            <DialogTitle sx={{ fontSize: '1rem', fontWeight: 700 }}>{request.title}</DialogTitle>
            <DialogContent>
                <DialogContentText sx={{ fontSize: '0.875rem' }}>{request.description}</DialogContentText>

                {request.expected !== undefined && (
                    <Box sx={{ mt: 2 }}>
                        <Typography sx={{ fontSize: '0.8125rem', color: 'text.secondary', mb: 0.5 }}>
                            {t('common.confirm.type_to_continue.prefix')} <Box component="span" sx={{ fontWeight: 700 }}>{request.expected}</Box> {t('common.confirm.type_to_continue.suffix')}
                        </Typography>
                        <TextField
                            autoFocus
                            fullWidth
                            size="small"
                            value={typed}
                            onChange={(e) => setTyped(e.target.value)}
                        />
                    </Box>
                )}
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={onClose}>
                    {t('common.confirm.cancel')}
                </Button>
                <Button
                    variant="contained"
                    color={destructive ? 'error' : 'primary'}
                    disabled={!matched}
                    onClick={confirm}
                >
                    {request.confirmText ?? t('common.confirm.remove')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
