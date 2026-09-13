import Box from '@mui/material/Box';
import type { ReactNode } from 'react';

interface ListRowProps {
    children: ReactNode;
    /** 右端に置く操作 */
    actions?: ReactNode;
}

/**
 * OutlinedList の1行。左に内容、右に操作を置く。
 */
export default function ListRow({ children, actions }: ListRowProps) {
    return (
        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}>
            {children}
            {actions}
        </Box>
    );
}
