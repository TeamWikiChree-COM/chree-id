import { usePage } from '@inertiajs/react';

/**
 * Turnstile のトークンが揃うまで送信を止めてよいかを返す。
 *
 * ウィジェットは非同期に解決するので、読み込みが終わる前に送信できると
 * 空のトークンがサーバに届く。**「人間による操作であることを確認できませんでした」が
 * 1回目だけ必ず出ていたのはこれ。** 押せない時間を作って防ぐ。
 *
 * Turnstile 未設定の環境ではサーバ側も検証を飛ばすので、常に false を返す。
 *
 * @param token フォームが持っている `cf-turnstile-response` の値
 * @returns 送信を止めるべきなら true
 */
export function useTurnstilePending(token: string): boolean {
    const { turnstileSiteKey } = usePage().props;

    return turnstileSiteKey !== null && token === '';
}
