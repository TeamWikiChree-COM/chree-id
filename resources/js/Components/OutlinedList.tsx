import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';

interface OutlinedListProps {
    children: ReactNode;
    /** 1件も無いときに出す文言。行が無いときだけ渡す */
    empty?: string | false;
}

/**
 * 枠で囲み、行の間に区切り線を引く一覧。
 */
export default function OutlinedList({ children, empty }: OutlinedListProps) {
    return (
        <Paper variant="outlined">
            <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                {empty && (
                    <Typography sx={{ px: 2, py: 1.5, fontSize: '0.9375rem', color: 'text.disabled' }}>
                        {empty}
                    </Typography>
                )}
                {children}
            </Stack>
        </Paper>
    );
}
