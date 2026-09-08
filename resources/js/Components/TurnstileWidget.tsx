import Box from '@mui/material/Box';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/** Cloudflare が読み込むスクリプト。同じ URL を二重に差し込まないよう id で判別する */
const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

interface TurnstileApi {
    render: (element: HTMLElement, options: { sitekey: string; callback: (token: string) => void }) => string;
    remove: (widgetId: string) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

/**
 * スクリプトを一度だけ読み込む。
 *
 * @returns turnstile が使えるようになったら解決する
 */
function loadScript(): Promise<void> {
    if (window.turnstile !== undefined) return Promise.resolve();

    const existing = document.getElementById(SCRIPT_ID);
    if (existing !== null) {
        return new Promise((resolve) => existing.addEventListener('load', () => resolve(), { once: true }));
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.src = SCRIPT_SRC;
        script.async = true;
        script.addEventListener('load', () => resolve(), { once: true });
        script.addEventListener('error', () => reject(new Error('Turnstile を読み込めませんでした')), { once: true });
        document.head.appendChild(script);
    });
}

interface TurnstileWidgetProps {
    /** 検証が通ったときに呼ばれる。トークンをフォームの値に入れること */
    onVerify: (token: string) => void;
}

/**
 * Turnstile のウィジェット。
 *
 * サイトキーが共有プロパティに無い環境では何も描画しない。
 * サーバ側も同じ条件で検証を飛ばすので、ローカルでは存在しないものとして扱える。
 */
export default function TurnstileWidget({ onVerify }: TurnstileWidgetProps) {
    const { turnstileSiteKey } = usePage().props;
    const containerRef = useRef<HTMLDivElement>(null);

    // onVerify は毎描画で作り直される。effect の依存に入れるとウィジェットが張り直されるので ref で渡す
    const onVerifyRef = useRef(onVerify);
    onVerifyRef.current = onVerify;

    useEffect(() => {
        if (turnstileSiteKey === null) return;

        const container = containerRef.current;
        if (container === null) return;

        let widgetId: string | null = null;
        let cancelled = false;

        void loadScript().then(() => {
            if (cancelled || window.turnstile === undefined) return;

            widgetId = window.turnstile.render(container, {
                sitekey: turnstileSiteKey,
                callback: (token) => onVerifyRef.current(token),
            });
        });

        return () => {
            cancelled = true;
            if (widgetId !== null) window.turnstile?.remove(widgetId);
        };
    }, [turnstileSiteKey]);

    if (turnstileSiteKey === null) return null;

    return <Box ref={containerRef} />;
}
