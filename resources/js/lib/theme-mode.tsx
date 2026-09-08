import { createContext, use, useEffect, useState } from 'react';
import type { PaletteMode } from '@mui/material/styles';

/**
 * 表示モードの保持。
 *
 * サーバに持たせない。ログインしていなくても切り替えたいし、
 * 端末ごとに違って構わない種類の設定なので、その端末のブラウザに置く。
 */

const KEY = 'chreeid.theme';

/**
 * @returns 保存された指定。無ければ OS の設定に従う
 */
function initialMode(): PaletteMode {
    // localStorage はプライベートウィンドウなどで例外を投げることがある
    try {
        const saved = window.localStorage.getItem(KEY);
        if (saved === 'light' || saved === 'dark') return saved;
    } catch {
        // 読めなければ OS の設定に落とす
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export interface ThemeMode {
    mode: PaletteMode;
    toggle: () => void;
}

/** app.tsx が値を入れ、ヘッダーなどが読む */
export const ThemeModeContext = createContext<ThemeMode>({ mode: 'light', toggle: () => {} });

/**
 * @returns 現在のモードと切り替え関数
 */
export function useThemeModeContext(): ThemeMode {
    return use(ThemeModeContext);
}

/**
 * 表示モードと、その切り替え。
 *
 * @returns 現在のモードと切り替え関数
 */
export function useThemeMode(): ThemeMode {
    const [mode, setMode] = useState<PaletteMode>('light');

    // 初期値はブラウザにしか無いので、描画後に読む。
    // サーバ側の HTML と食い違わせないための遅延でもある
    useEffect(() => setMode(initialMode()), []);

    const toggle = (): void => {
        setMode((current) => {
            const next: PaletteMode = current === 'dark' ? 'light' : 'dark';

            try {
                window.localStorage.setItem(KEY, next);
            } catch {
                // 保存できなくても、その場の切り替えは効かせる
            }

            return next;
        });
    };

    return { mode, toggle };
}
