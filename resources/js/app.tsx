import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider } from '@mui/material/styles';
import { createRoot } from 'react-dom/client';
import theme from './theme';

createInertiaApp({
    title: (title: string) => (title ? `${title} - ChreeID` : 'ChreeID'),

    resolve: (name: string) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];

        if (page === undefined) throw new Error(`ページが見つかりません: ${name}`);

        return page;
    },

    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider theme={theme}>
                <CssBaseline />
                <App {...props} />
            </ThemeProvider>,
        );
    },
});
