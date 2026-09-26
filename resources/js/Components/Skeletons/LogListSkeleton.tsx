import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';

/** ログ1件分。LogEntryRow と同じ余白にして、差し替わったときに行の高さがずれないようにする */
function LogEntrySkeleton() {
    return (
        <Box sx={{ px: 2, py: 1.5 }}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <Skeleton variant="rounded" width={64} height={20} />
                <Skeleton variant="text" width={140} sx={{ fontSize: '0.8125rem' }} />
                <Skeleton variant="text" width={60} sx={{ fontSize: '0.8125rem' }} />
            </Stack>
            <Skeleton variant="text" width="85%" sx={{ mt: 0.5, fontSize: '0.875rem' }} />
        </Box>
    );
}

/**
 * ログ一覧の読み込み中表示。
 *
 * @param count 並べる行数
 */
export default function LogListSkeleton({ count = 8 }: { count?: number }) {
    return (
        <Paper variant="outlined">
            <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                {Array.from({ length: count }, (_, i) => <LogEntrySkeleton key={i} />)}
            </Stack>
        </Paper>
    );
}
