import Box from '@mui/material/Box';

/**
 * 一覧の行と行の間に引く区切り線。Stack の divider に渡す。
 */
const RowDivider = () => {
    return <Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />;
}

export default RowDivider;
