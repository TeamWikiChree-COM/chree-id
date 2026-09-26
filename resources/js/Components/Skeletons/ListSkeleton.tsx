import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';

interface ListSkeletonProps {
    /** 並べる行数 */
    count?: number;
    /** 行頭にチップを置くか。実物にある場合だけ付けて、差し替わったときの高さのずれを減らす */
    chip?: boolean;
}

/** 1行分。見出しと補足の2段 */
const RowSkeleton = ({ chip }: { chip: boolean }) => {
    return (
        <Box sx={{ px: 2, py: 1.25 }}>
            {chip && <Skeleton variant="rounded" width={72} height={20} sx={{ mb: 0.5 }} />}
            <Skeleton variant="text" width="45%" sx={{ fontSize: '0.9375rem' }} />
            <Skeleton variant="text" width={120} sx={{ fontSize: '0.75rem' }} />
        </Box>
    );
};

/**
 * 枠付き一覧の読み込み中表示。
 *
 * @param props 行数とチップの有無
 */
const ListSkeleton = ({ count = 5, chip = false }: ListSkeletonProps) => {
    return (
        <Paper variant="outlined" sx={{ mb: 2 }}>
            <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                {Array.from({ length: count }, (_, i) => <RowSkeleton key={i} chip={chip} />)}
            </Stack>
        </Paper>
    );
};

export default ListSkeleton;
