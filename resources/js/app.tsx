import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider } from '@mui/material/styles';
import { createRoot } from 'react-dom/client';
import { useMemo } from 'react';
import { buildTheme } from './theme';
import { ThemeModeContext, useThemeMode } from './lib/theme-mode';
import { t } from './lib/i18n';

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

/** ビルド時に .env の APP_NAME から埋め込まれる。タイトルは Head より前に確定させる必要があるため */
const appName = import.meta.env.VITE_APP_NAME ?? 'ChreeID';

createInertiaApp({
    title: (title: string) => (title ? `${title} - ${appName}` : appName),

    resolve: (name: string) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];

        if (page === undefined) throw new Error(t('common.error.page_not_found', { name }));

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
