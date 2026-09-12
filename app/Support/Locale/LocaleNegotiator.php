<?php
namespace App\Support\Locale;

use Illuminate\Http\Request;

/**
 * その要求をどの言語で返すかを決める。
 *
 * 優先順位は「本人が選んだもの → ブラウザの設定 → 既定」。
 * **既定はブラウザの Accept-Language。** 選ばないままにしておけば端末に追従する。
 *
 * 本人が選んだものの置き場は2つある。ログイン中は認証主体の行 (端末を変えても
 * 付いてくる)、未ログインは Cookie (ログイン画面でも切り替えられる必要がある)。
 */
final class LocaleNegotiator {
    /** 未ログインのときに選択を覚えておく Cookie */
    public const COOKIE = 'chreeid_locale';

    public function __construct(
        private readonly Locales $locales,
        private readonly StoredLocale $stored,
    ) {}

    /**
     * @param Request $request 来ている要求
     * @return string 使う言語
     */
    public function resolve(Request $request): string {
        $cookie = $request->cookie(self::COOKIE);
        $chosen = $this->stored->forCurrentUser() ?? (is_string($cookie) ? $cookie : null);

        if ($chosen !== null && $this->locales->allows($chosen)) return $chosen;

        return $this->fromBrowser($request) ?? $this->locales->fallback();
    }

    /**
     * Accept-Language から、出せる言語のうち最も好まれているものを選ぶ。
     *
     * @param Request $request 来ている要求
     * @return string|null 決められなければ null
     */
    private function fromBrowser(Request $request): ?string {
        foreach ($this->preferences($request) as $tag) {
            // "ja-JP" は "ja" として扱う。地域まで分けた辞書は持っていない
            $language = strtolower(explode('-', $tag)[0]);

            if ($this->locales->allows($language)) return $language;
        }

        return null;
    }

    /**
     * @param Request $request 来ている要求
     * @return list<string> 好まれている順の言語タグ
     */
    private function preferences(Request $request): array {
        $header = $request->header('Accept-Language');
        if (!is_string($header) || $header === '') return [];

        $weighted = [];

        foreach (explode(',', $header) as $index => $part) {
            $pieces = explode(';q=', trim($part));
            $tag = trim($pieces[0]);

            if ($tag === '' || $tag === '*') continue;

            // q が無ければ 1.0。同じ q なら書かれた順を保つ
            $weighted[] = ['tag' => $tag, 'q' => (float) ($pieces[1] ?? 1.0), 'order' => $index];
        }

        usort($weighted, fn (array $a, array $b) => [$b['q'], $a['order']] <=> [$a['q'], $b['order']]);

        return array_column($weighted, 'tag');
    }
}
