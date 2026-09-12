import Box from '@mui/material/Box';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { t } from '../lib/i18n';

/** Cloudflare が読み込むスクリプト。同じ URL を二重に差し込まないよう id で判別する */
const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

interface TurnstileOptions {
    sitekey: string;
    callback: (token: string) => void;
    /** 期限切れ。既定では 300 秒で失効する */
    'expired-callback': () => void;
    /** チャレンジ自体が失敗した */
    'error-callback': () => void;
}

interface TurnstileApi {
    render: (element: HTMLElement, options: TurnstileOptions) => string;
    reset: (widgetId: string) => void;
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
        script.addEventListener('error', () => reject(new Error(t('common.turnstile.load_error'))), { once: true });
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
 *
 * **トークンは一度きり。** 送信が失敗したら使用済みのものが手元に残るので、
 * 検証エラーを見たらウィジェットを張り直して取り直す。取り直すまでの間は
 * 空文字を渡し、呼び出し側が送信を止められるようにする。
 */
export default function TurnstileWidget({ onVerify }: TurnstileWidgetProps) {
    const { turnstileSiteKey, errors } = usePage().props;
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);

    // onVerify は毎描画で作り直される。effect の依存に入れるとウィジェットが張り直されるので ref で渡す
    const onVerifyRef = useRef(onVerify);
    onVerifyRef.current = onVerify;

    useEffect(() => {
        if (turnstileSiteKey === null) return;

        const container = containerRef.current;
        if (container === null) return;

        let cancelled = false;

        void loadScript().then(() => {
            if (cancelled || window.turnstile === undefined) return;

            widgetIdRef.current = window.turnstile.render(container, {
                sitekey: turnstileSiteKey,
                callback: (token) => onVerifyRef.current(token),
                // 失効・失敗したものを送ると「人間による操作が確認できない」で弾かれる。
                // 手元から消して、取り直せるまで送信させない
                'expired-callback': () => onVerifyRef.current(''),
                'error-callback': () => onVerifyRef.current(''),
            });
        });

        return () => {
            cancelled = true;
            if (widgetIdRef.current !== null) window.turnstile?.remove(widgetIdRef.current);
            widgetIdRef.current = null;
        };
    }, [turnstileSiteKey]);

    // 送信が弾かれたということは、渡したトークンは使い切られている。
    // そのまま送り直すと二度目も必ず落ちるので、ここで取り直す
    useEffect(() => {
        if (Object.keys(errors).length === 0) return;
        if (widgetIdRef.current === null) return;

        onVerifyRef.current('');
        window.turnstile?.reset(widgetIdRef.current);
    }, [errors]);

    if (turnstileSiteKey === null) return null;

    return <Box ref={containerRef} />;
}
