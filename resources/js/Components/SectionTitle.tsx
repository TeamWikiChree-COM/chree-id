import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';

interface SectionTitleProps {
    children: ReactNode;
    /** 見出しの右に薄く添える補足 (件数など) */
    note?: ReactNode;
}

/**
 * 節の見出し。
 *
 * 左の縦バーが「ここから新しい塊」の合図になる。
 * ChreeID は小さな項目が縦に並ぶ画面が多く、枠だけだとどこで切れているか追いにくいので、
 * 始まりを見出しで、終わりを枠で示す。
 */
export default function SectionTitle({ children, note }: SectionTitleProps) {
    return (
        <Typography
            component="h2"
            sx={{
                display: 'flex',
                alignItems: 'center',
                gap: 1,
                mt: 4,
                mb: 1.25,
                fontSize: '0.875rem',
                fontWeight: 700,
            }}
        >
            <Box
                aria-hidden
                sx={{ width: 3, height: '1em', borderRadius: 1, bgcolor: 'primary.main', flex: 'none' }}
            />
            {children}
            {note !== undefined && (
                <Box component="span" sx={{ fontWeight: 400, fontSize: '0.8125rem', color: 'text.disabled' }}>
                    {note}
                </Box>
            )}
        </Typography>
    );
}
