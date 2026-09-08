import { createTheme } from '@mui/material/styles';
import type { Shadows } from '@mui/material/styles';

/**
 * ChreeID のテーマ。
 *
 * 角丸・影・カードを抑える方針を既定値として持たせる。
 * 画面ごとに sx で指定して回ると必ずズレるので、ここで縛る。
 */
const theme = createTheme({
    // 今はライトのみ。ダークは後で palette を切り替えて対応する
    palette: {
        mode: 'light',
    },

    shape: {
        // 角丸は基本なし。ボタンだけ下で少しだけ付ける
        borderRadius: 0,
    },

    // MUI は elevation 0-24 を影として持つが、全部なしにする
    shadows: Array(25).fill('none') as unknown as Shadows,

    typography: {
        fontFamily: [
            '-apple-system',
            'BlinkMacSystemFont',
            '"Segoe UI"',
            '"Helvetica Neue"',
            '"Hiragino Sans"',
            '"Noto Sans JP"',
            'Meiryo',
            'sans-serif',
        ].join(','),
        button: {
            // 大文字化しない
            textTransform: 'none',
        },
    },

    components: {
        MuiButton: {
            defaultProps: {
                disableElevation: true,
            },
            styleOverrides: {
                root: {
                    borderRadius: 2,
                },
            },
        },
        MuiPaper: {
            defaultProps: {
                elevation: 0,
                variant: 'outlined',
            },
            styleOverrides: {
                root: {
                    // MUI は elevation を backgroundImage のグラデーションで表現する。それを消す
                    backgroundImage: 'none',
                },
            },
        },
        MuiTextField: {
            defaultProps: {
                size: 'small',
                fullWidth: true,
            },
        },
    },
});

export default theme;
