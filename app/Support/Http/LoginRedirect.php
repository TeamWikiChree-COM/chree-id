<?php
namespace App\Support\Http;

use Illuminate\Http\RedirectResponse;

/**
 * ログインを挟んだときの戻り先。
 *
 * URL を直接開いた人を `/login` へ送ったあと、認証が済んだら元の場所へ戻す。
 * 送りっぱなしにすると、ブックマークや共有リンクから来た人が毎回ダッシュボードに
 * 落ちて、自分で辿り直すことになる。
 *
 * **Laravel の `redirect()->guest()` は使わない。** あれは GET 以外のとき
 * Referer を戻り先として覚える。Referer は相手が好きな値を入れられるので、
 * ログイン直後に外部サイトへ飛ばせてしまう。
 */
final class LoginRedirect {
    /** 戻り先を置くセッションキー。Laravel の intended() と同じものを使う */
    private const KEY = 'url.intended';

    /**
     * ログインしていない人を送り出す。いまの場所を覚えてから送る。
     *
     * 引数で受け取らないのは、呼ぶのが画面を出すだけのメソッド (`index()` など)
     * ばかりで、この一行のために全部の引数を増やすことになるため。
     * Laravel の `redirect()->guest()` も同じく現在の要求を見る。
     *
     * @return RedirectResponse
     */
    public static function guest(): RedirectResponse {
        $request = request();

        // 覚えるのは GET で開かれた画面だけ。POST の戻り先は「送信先」であって
        // 見せたい画面ではないし、Referer 由来の値を混ぜたくない
        if ($request->isMethod('GET') && !$request->expectsJson()) {
            $request->session()->put(self::KEY, $request->fullUrl());
        }

        return redirect('/login');
    }

    /**
     * ログインが済んだあとの行き先。
     *
     * @param string $fallback 覚えていないときの行き先
     * @return string
     */
    public static function intended(string $fallback = '/'): string {
        $request = request();

        $intended = $request->session()->pull(self::KEY);
        if (!is_string($intended)) return $fallback;

        // 自分のところ以外は捨てる。覚えるのは自分の URL だけのはずだが、
        // 出口で確かめておかないと、書き込む経路が増えたときに素通りする
        $host = parse_url($intended, PHP_URL_HOST);

        return $host === null || $host === $request->getHost() ? $intended : $fallback;
    }
}
