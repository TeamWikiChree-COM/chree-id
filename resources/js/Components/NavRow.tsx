import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Icon from './Icon';

interface NavRowProps {
    /** Font Awesome の名前 */
    icon: string;
    title: string;
    description: string;
    href: string;
}

/**
 * 一覧の中の、行そのものが行き先になっている1行。
 */
export default function NavRow({ icon, title, description, href }: NavRowProps) {
    const go = (): void => router.get(href);

    return (
        <Box
            role="button"
            tabIndex={0}
            onClick={go}
            onKeyDown={(event) => { if (event.key === 'Enter') go(); }}
            sx={{
                display: 'flex',
                alignItems: 'center',
                gap: 2,
                px: 2,
                py: 1.75,
                cursor: 'pointer',
                '&:hover': { bgcolor: 'action.hover' },
            }}
        >
            <Icon name={icon} sx={{ width: 20, textAlign: 'center', color: 'text.disabled' }} />
            <Box sx={{ flex: 1 }}>
                <Typography sx={{ fontSize: '0.9375rem' }}>{title}</Typography>
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>{description}</Typography>
            </Box>
            <Icon name="chevron-right" sx={{ fontSize: '0.75rem', color: 'text.disabled' }} />
        </Box>
    );
}
