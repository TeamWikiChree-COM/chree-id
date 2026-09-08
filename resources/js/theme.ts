import { createTheme } from '@mui/material/styles';
import type { PaletteMode, Theme } from '@mui/material/styles';

/**
 * ChreeID のテーマ。
 *
 * 色と寸法は DokuFarm、装飾とボタンの質感は ModParks に合わせている。
 * 3サービスを行き来したときに同じ家族に見えることを狙っているので、
 * 画面ごとに sx で足すのではなく、ここで既定を決めきる。
 *
 * 設計は prototype/design/D 参照。
 */

/** 面と枠。ダークは DokuFarm の値をそのまま使わない (下記) */
const surfaces = {
    light: {
        page: '#f9fafb',
        paper: '#ffffff',
        hover: '#f3f4f6',
        border: '#e5e7eb',
        borderStrong: '#d1d5db',
        header: 'rgba(255, 255, 255, 0.82)',
        cardShadow: '0 1px 2px rgba(0, 0, 0, 0.04)',
    },
    /*
     * DokuFarm のダークは面 #0a0a0a / ページ #0d0d0d でコントラスト比 1.02:1、
     * つまり実質同じ色で、カードの枠線だけが境目を作っていた。
     * 面を持ち上げ、ページを沈め、枠を面よりはっきり明るくしている。
     * 段差の大きさは ModParks のダーク (1.17〜1.22:1) に合わせた。
     */
    dark: {
        page: '#0e1014',
        paper: '#1a1e25',
        hover: '#252a33',
        border: '#333a45',
        borderStrong: '#454e5c',
        header: 'rgba(26, 30, 37, 0.82)',
        cardShadow: 'none',
    },
} as const;

const palettes = {
    light: {
        primary: { main: '#2563eb', dark: '#1d4ed8', light: '#3b82f6', contrastText: '#ffffff' },
        text: { primary: '#1f2937', secondary: '#6b7280', disabled: '#9ca3af' },
        success: { main: '#059669' },
        warning: { main: '#d97706' },
        error: { main: '#dc2626' },
    },
    dark: {
        primary: { main: '#60a5fa', dark: '#3b82f6', light: '#93c5fd', contrastText: '#0e1014' },
        text: { primary: '#e5e7eb', secondary: '#b6bec9', disabled: '#9aa3b0' },
        success: { main: '#34d399' },
        warning: { main: '#fbbf24' },
        error: { main: '#f87171' },
    },
} as const;

/**
 * @param mode 表示モード
 * @returns その モード のテーマ
 */
export function buildTheme(mode: PaletteMode): Theme {
    const surface = surfaces[mode];
    const palette = palettes[mode];

    return createTheme({
        palette: {
            mode,
            ...palette,
            background: { default: surface.page, paper: surface.paper },
            divider: surface.border,
        },

        // DokuFarm の 8px と ModParks の 4px の間を取る
        shape: { borderRadius: 6 },

        typography: {
            fontFamily: [
                '-apple-system',
                'BlinkMacSystemFont',
                '"Segoe UI"',
                'Roboto',
                '"Hiragino Sans"',
                '"Noto Sans JP"',
                'Meiryo',
                'sans-serif',
            ].join(','),
            button: { textTransform: 'none', fontWeight: 600 },
        },

        components: {
            MuiCssBaseline: {
                styleOverrides: {
                    html: { colorScheme: mode },
                    body: { backgroundColor: surface.page },
                },
            },

            MuiButton: {
                defaultProps: { disableElevation: true },
                styleOverrides: {
                    root: { transition: 'background-color 0.2s, border-color 0.2s, color 0.2s' },
                    sizeSmall: { height: 32, padding: '0 12px', fontSize: '0.8125rem' },
                    sizeMedium: { height: 40, padding: '0 16px', fontSize: '0.875rem' },
                    sizeLarge: { height: 48, padding: '0 22px', fontSize: '0.9375rem' },
                    outlined: { borderColor: surface.borderStrong },
                },
            },

            MuiPaper: {
                defaultProps: { elevation: 0 },
                styleOverrides: {
                    // MUI は elevation を backgroundImage のグラデーションで表現する。それを消す
                    root: { backgroundImage: 'none' },
                    outlined: { borderColor: surface.border, boxShadow: surface.cardShadow },
                },
            },

            // 入力欄は小さい方を既定にする。ModParks も同じ
            MuiTextField: { defaultProps: { size: 'small', fullWidth: true } },
            MuiFormControl: { defaultProps: { size: 'small' } },
            MuiSelect: { defaultProps: { size: 'small' } },

            MuiOutlinedInput: {
                styleOverrides: {
                    root: {
                        '& .MuiOutlinedInput-notchedOutline': {
                            borderColor: surface.borderStrong,
                            transition: 'border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out',
                        },
                        '&:hover .MuiOutlinedInput-notchedOutline': {
                            borderColor: mode === 'light' ? '#9ca3af' : '#6b7280',
                        },
                        '&.Mui-focused': {
                            '& .MuiOutlinedInput-notchedOutline': {
                                borderColor: palette.primary.main,
                                borderWidth: '1px',
                                boxShadow: `0 0 0 1px ${palette.primary.main}`,
                            },
                        },
                    },
                    input: {
                        outline: 'none',
                    },
                },
            },

            MuiMenuItem: {
                styleOverrides: {
                    root: {
                        transition: 'background-color 0.15s ease-in-out, color 0.15s ease-in-out',
                    },
                },
            },

            MuiChip: {
                styleOverrides: {
                    root: { fontWeight: 600 },
                    sizeSmall: { height: 20, fontSize: '0.75rem' },
                },
            },

            MuiTab: {
                styleOverrides: {
                    root: { minHeight: 48, fontWeight: 500, fontSize: '0.875rem' },
                },
            },

            MuiTooltip: {
                defaultProps: { arrow: false },
            },

            MuiAlert: {
                styleOverrides: {
                    root: { fontSize: '0.875rem' },
                },
            },

            MuiLink: {
                defaultProps: { underline: 'hover' },
            },

            MuiDivider: {
                styleOverrides: { root: { borderColor: surface.border } },
            },
        },
    });
}

/** ヘッダーの半透明の色。AppHeader から使う */
export const headerBackground = (mode: PaletteMode): string => surfaces[mode].header;

export default buildTheme('light');
