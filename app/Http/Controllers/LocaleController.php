<?php

namespace App\Http\Controllers;

use App\Support\Locale\LocaleNegotiator;
use App\Support\Locale\Locales;
use App\Support\Locale\StoredLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * 表示言語の切り替え。
 *
 * ログイン中かどうかで置き場が違う。ログイン中は認証主体の行に持たせて
 * 端末を越えて付いてくるようにし、未ログインは Cookie に置く
 * (ログイン画面でも切り替えられる必要があるため)。
 */
class LocaleController {
    public function __construct(
        private readonly Locales $locales,
        private readonly StoredLocale $stored,
    ) {}

    /**
     * 選んだ言語に切り替える。
     *
     * **画面ごと読み込み直させる (Inertia::location)。** 文言の辞書はビルド時に
     * バンドルへ畳み込まれていて、どちらを使うかは最初の読み込みで決まる
     * (resources/js/lib/i18n.ts)。Inertia の部分更新で戻ると、props の locale
     * だけが変わって画面の文字は元の言語のまま残る。
     *
     * @param Request $request 選んだ言語を含む要求
     * @return Response
     */
    public function update(Request $request): Response {
        $locale = $request->string('locale')->toString();

        // 知らない名前は黙って無視する。押せるのはこちらが出したボタンだけなので、
        // ここに来る不正な値は手で叩かれたものしかない
        if (!$this->locales->allows($locale)) return back();

        $this->stored->remember($locale);

        // Cookie も必ず置く。ログアウトしたあとも選んだ言語で出すため。
        // queue で積むのは、Inertia::location が返す応答の型に依らず付けられるから
        Cookie::queue(cookie()->forever(LocaleNegotiator::COOKIE, $locale));

        return Inertia::location(url()->previous());
    }
}
