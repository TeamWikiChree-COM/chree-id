import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Paper from '@mui/material/Paper';
import Slide from '@mui/material/Slide';
import Typography from '@mui/material/Typography';
import { t } from '../lib/i18n';

interface StickySaveBarProps {
    /** 未保存の変更があるとき true。false の間はバーごと隠れる */
    open: boolean;
    saving?: boolean;
    onSave: () => void;
    /** 省略すると「取り消す」ボタンを出さない */
    onDiscard?: () => void;
    disabled?: boolean;
}

/**
 * 設定画面で共通して使う、未保存の変更があるときだけ浮き上がる保存バー。
 * 各フォームに常設の保存ボタンを置く代わりにこれを使う。
 */
export default function StickySaveBar({ open, saving = false, onSave, onDiscard, disabled = false }: StickySaveBarProps) {
    return (
        <Slide direction="up" in={open} mountOnEnter unmountOnExit>
            <Box
                sx={{
                    position: 'fixed',
                    left: 0,
                    right: 0,
                    bottom: 0,
                    zIndex: (theme) => theme.zIndex.modal - 1,
                    display: 'flex',
                    justifyContent: 'center',
                    px: 2,
                    pb: 'calc(16px + env(safe-area-inset-bottom))',
                    pointerEvents: 'none',
                }}
            >
                <Paper
                    variant="outlined"
                    sx={{
                        pointerEvents: 'auto',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 2,
                        width: '100%',
                        maxWidth: 640,
                        px: 2,
                        py: 1.25,
                        borderRadius: 2,
                    }}
                >
                    <Typography variant="body2" sx={{ flex: 1, minWidth: 0 }} noWrap>
                        {t('settings.common.unsaved_changes')}
                    </Typography>
                    {onDiscard && (
                        <Button size="small" color="inherit" onClick={onDiscard} disabled={saving}>
                            {t('settings.common.discard')}
                        </Button>
                    )}
                    <Button
                        size="small"
                        variant="contained"
                        onClick={onSave}
                        disabled={saving || disabled}
                        startIcon={saving ? <CircularProgress size={16} color="inherit" /> : undefined}
                    >
                        {saving ? t('settings.common.saving') : t('settings.common.save')}
                    </Button>
                </Paper>
            </Box>
        </Slide>
    );
}
