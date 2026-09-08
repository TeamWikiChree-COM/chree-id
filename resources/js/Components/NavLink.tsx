import Typography from '@mui/material/Typography';
import InertiaLink from './InertiaLink';

interface NavLinkProps {
    href: string;
    children: string;
}

/**
 * ヘッダーの行き先1つ分。
 */
export default function NavLink({ href, children }: NavLinkProps) {
    return (
        <Typography
            component={InertiaLink}
            href={href}
            sx={{
                px: 1.5,
                py: 0.75,
                fontSize: '0.875rem',
                color: 'text.secondary',
                textDecoration: 'none',
                borderRadius: 1,
                '&:hover': { bgcolor: 'action.hover', color: 'text.primary' },
            }}
        >
            {children}
        </Typography>
    );
}
