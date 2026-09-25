import Box from '@mui/material/Box';
import type { ReactNode } from 'react';
import Icon from './Icon';

interface ListRowProps {
    children: ReactNode;
    /**
     * 右端に置くもの。
     *
     * 操作ボタンを並べるのは1つまで。2つ以上あるなら onClick で
     * ダイアログか専用ページを開き、そこへまとめる。
     */
    actions?: ReactNode;
    /** 渡すと行そのものが押せるようになる */
    onClick?: () => void;
}

/**
 * OutlinedList の1行。左に内容、右に操作を置く。
 */
export default function ListRow({ children, actions, onClick }: ListRowProps) {
    const clickable = onClick !== undefined;

    return (
        <Box
            role={clickable ? 'button' : undefined}
            tabIndex={clickable ? 0 : undefined}
            onClick={onClick}
            onKeyDown={(event) => { if (clickable && event.key === 'Enter') onClick(); }}
            sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 2,
                px: 2,
                py: 1.5,
                ...(clickable && { cursor: 'pointer', '&:hover': { bgcolor: 'action.hover' } }),
            }}
        >
            {children}
            {actions}
            {clickable && actions === undefined && (
                <Icon name="chevron-right" sx={{ fontSize: '0.75rem', color: 'text.disabled', flexShrink: 0 }} />
            )}
        </Box>
    );
}
