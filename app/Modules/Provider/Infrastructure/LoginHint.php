<?php
namespace App\Modules\Provider\Infrastructure;

use Illuminate\Http\Request;

/**
 * サービスが「この人です」と添えてきたアドレスを、ログイン画面まで運ぶ。
 *
 * OIDC の `login_hint` (OIDC Core 3.1.2.1)。サービス側で既にアドレスを
 * 入力させている場合に、こちらでもう一度打たせないためのもの。
 *
 * **ヒントであって認証ではない。** 埋めるだけで、誰であるかの判断には使わない。
 */
class LoginHint {
    private const KEY = 'oauth.login_hint';

    public function __construct(private readonly Request $request) {}

    /**
     * 認可リクエストに付いていれば覚えておく。
     *
     * @return void
     */
    public function remember(): void {
        $hint = $this->request->string('login_hint')->trim()->toString();

        if ($hint === '') return;

        // 画面に出す値なので、形になっていないものは持ち回らない
        if (filter_var($hint, FILTER_VALIDATE_EMAIL) === false) return;

        $this->request->session()->put(self::KEY, $hint);
    }

    /**
     * ログイン画面が使う。一度使ったら捨てる。
     *
     * @return string|null
     */
    public function pull(): ?string {
        $hint = $this->request->session()->pull(self::KEY);

        return is_string($hint) ? $hint : null;
    }
}
