import Box, { type BoxProps } from '@mui/material/Box';
import type { SxProps, Theme } from '@mui/material/styles';

/**
 * 枠線で囲んだ区画の見た目。サイト全体の枠はこれに揃える。
 *
 * Box 以外 (ButtonBase など) に同じ枠を付けたいときはこれを sx に混ぜる。
 */
export const outlinedBoxSx = {
    border: '1px solid',
    borderColor: 'divider',
    borderRadius: 2,
    p: 2,
} as const;

/**
 * 枠線で囲んだ区画。
 */
const OutlinedBox = ({ sx, ...props }: BoxProps) => {
    const merged: SxProps<Theme> = [outlinedBoxSx, ...(Array.isArray(sx) ? sx : [sx])];

    return <Box sx={merged} {...props} />;
}

export default OutlinedBox;
