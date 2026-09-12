import { useForm } from '@inertiajs/react';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import TextField from '@mui/material/TextField';
import { useEffect } from 'react';
import type { CredentialSummary } from '../../types';

interface RenameCredentialDialogProps {
    /** 名前を変える対象。null なら閉じている */
    credential: CredentialSummary | null;
    onClose: () => void;
}

/**
 * パスキーの名前を変える。
 *
 * 端末名は登録時に User-Agent から当てているだけで外すことがある。
 * 「Chrome (Windows)」が2つ並んだときに、本人にしか区別が付けられない。
 */
export default function RenameCredentialDialog({ credential, onClose }: RenameCredentialDialogProps) {
    const form = useForm({ id: '', label: '' });

    // 開くたびに今の名前を入れ直す。前回の入力が残っていると別の鍵の名前を上書きしうる
    useEffect(() => {
        if (credential === null) return;

        form.setData({ id: credential.id, label: credential.label ?? '' });
    }, [credential]);

    const submit = (): void => {
        form.post('/security/credentials/rename', { onSuccess: onClose });
    };

    return (
        <Dialog open={credential !== null} onClose={onClose} fullWidth maxWidth="xs">
            <DialogTitle sx={{ fontSize: '1rem' }}>パスキーの名前</DialogTitle>
            <DialogContent>
                <TextField
                    autoFocus
                    fullWidth
                    label="名前"
                    value={form.data.label}
                    onChange={(e) => form.setData('label', e.target.value)}
                    error={Boolean(form.errors.label)}
                    helperText={form.errors.label ?? 'どの端末の鍵か分かる名前にしてください'}
                    sx={{ mt: 1 }}
                />
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={onClose}>
                    やめる
                </Button>
                <Button variant="contained" onClick={submit} disabled={form.processing}>
                    保存する
                </Button>
            </DialogActions>
        </Dialog>
    );
}
