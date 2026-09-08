import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider } from '@mui/material/styles';
import { createRoot } from 'react-dom/client';
import { useMemo } from 'react';
import { buildTheme } from './theme';
import { ThemeModeContext, useThemeMode } from './lib/theme-mode';

/**
 * テーマの供給。
 *
 * モードはブラウザにしか無いので、ここで持って全ページに配る。
 */
function Root({ children }: { children: ReactNode }) {
    const { mode, toggle } = useThemeMode();
    const theme = useMemo(() => buildTheme(mode), [mode]);
    const value = useMemo(() => ({ mode, toggle }), [mode, toggle]);

    return (
        <ThemeModeContext value={value}>
            <ThemeProvider theme={theme}>
                <CssBaseline />
                {children}
            </ThemeProvider>
        </ThemeModeContext>
    );
}

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
            <Root>
                <App {...props} />
            </Root>,
        );
    },
});
