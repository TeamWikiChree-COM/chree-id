import Box from '@mui/material/Box';
import MuiBreadcrumbs from '@mui/material/Breadcrumbs';
import Typography from '@mui/material/Typography';
import Icon from './Icon';
import InertiaLink from './InertiaLink';

export interface Crumb {
    label: string;
    /** 省略すると現在地として描く。最後の項目は url があっても現在地扱い */
    href?: string;
}

interface BreadcrumbsProps {
    items: Crumb[];
}

/**
 * パンくず。
 *
 * DokuFarm の partial と同じ考え方で、url を持たない項目と最後の項目を現在地にする。
 */
export default function Breadcrumbs({ items }: BreadcrumbsProps) {
    const last = items.length - 1;

    return (
        <MuiBreadcrumbs
            aria-label="パンくず"
            separator={<Icon name="chevron-right" sx={{ fontSize: '0.6875rem' }} />}
            sx={{ pt: 1.5, pb: 0.5, fontSize: '0.875rem', color: 'text.secondary' }}
        >
            {items.map((item, index) => {
                if (index === last || item.href === undefined) {
                    return (
                        <Typography key={item.label} aria-current="page" sx={{ fontSize: 'inherit', color: 'text.secondary' }}>
                            {item.label}
                        </Typography>
                    );
                }

                return (
                    <Box
                        key={item.label}
                        component={InertiaLink}
                        href={item.href}
                        sx={{ fontSize: 'inherit', color: 'primary.main', textDecoration: 'none', '&:hover': { textDecoration: 'underline' } }}
                    >
                        {item.label}
                    </Box>
                );
            })}
        </MuiBreadcrumbs>
    );
}
