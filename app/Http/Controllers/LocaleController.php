<?php

namespace App\Http\Controllers;

use App\Support\Locale\LocaleNegotiator;
use App\Support\Locale\Locales;
use App\Support\Locale\StoredLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
     * @param Request $request 選んだ言語を含む要求
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse {
        $locale = $request->string('locale')->toString();

        // 知らない名前は黙って無視する。押せるのはこちらが出したボタンだけなので、
        // ここに来る不正な値は手で叩かれたものしかない
        if (!$this->locales->allows($locale)) return back();

        $this->stored->remember($locale);

        // Cookie も必ず置く。ログアウトしたあとも選んだ言語で出すため
        return back()->withCookie(cookie()->forever(LocaleNegotiator::COOKIE, $locale));
    }
}
