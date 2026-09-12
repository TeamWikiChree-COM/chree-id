import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';

interface DeviceRowProps {
    /** 「Chrome (Windows)」のような表示名 */
    label: string;
    /** 下に小さく添える情報。null や空文字は落とす */
    detail: (string | null | false)[];
    /** 名前の右に付ける印。不要なら null */
    badge?: ReactNode;
    action: ReactNode;
}

/**
 * 端末一覧の1行。
 *
 * ログイン中の端末と信頼済みの端末で、出す情報は違うが並びは同じにする。
 * 揃えておかないと、別画面のように見えて取り違える。
 */
export default function DeviceRow({ label, detail, badge = null, action }: DeviceRowProps) {
    const parts = detail.filter((part): part is string => typeof part === 'string' && part !== '');

    return (
        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}>
            <Box>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                    <Typography sx={{ fontSize: '0.9375rem' }}>{label}</Typography>
                    {badge}
                </Stack>
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                    {parts.join(' ・ ')}
                </Typography>
            </Box>
            {action}
        </Box>
    );
}
