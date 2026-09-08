import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';
import AppHeader from './AppHeader';
import Breadcrumbs from './Breadcrumbs';
import type { Crumb } from './Breadcrumbs';

interface AppLayoutProps {
    /** ブラウザのタイトルと見出しを兼ねる */
    title: string;
    /** 見出しの下に置く一文 */
    lead?: string;
    crumbs?: Crumb[];
    children: ReactNode;
}

/**
 * ログイン後の画面の外枠。
 *
 * ヘッダー・パンくず・見出しまでを揃える。画面ごとに組み立てるとズレるので、ここに固定する。
 */
export default function AppLayout({ title, lead, crumbs, children }: AppLayoutProps) {
    return (
        <>
            <Head title={title} />
            <AppHeader />


            <Caontainera maxWidth="md">
            <Typography
                variant="h1"
                sx={{
                    fontSize: '1.375rem',
                    fontWeight: 700,
                    pb: 1,
                    mb: lead === undefined ? 1.5 : 1,
                    borderBottom: '2px solid',
                    borderColor: 'divider',
                }}
            >
                {title}
            </Typography>
            </Caontainera>

            {crumbs !== undefined && (
                <Container maxWidth="md">
                    <Breadcrumbs items={crumbs} />
                </Container>
            )}

            <Container maxWidth="md" sx={{ pt: crumbs === undefined ? 3 : 1, pb: 8 }}>
                {lead !== undefined && (
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', mb: 2 }}>{lead}</Typography>
                )}

                <Box>{children}</Box>
            </Container>
        </>
    );
}
