<?php
namespace App\Support\Session;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * 「このブラウザは一度ログインした」という印。
 *
 * **セッションとは別の場所に置く。** セッションが失われたことを知るには、
 * セッションの外に手がかりが要る。中に置くと、失われたときに印ごと消える。
 *
 * 印そのものに意味は無く、値も固定。これだけでは何の権限にもならない。
 */
final class SignedInMarker {
    private const COOKIE = 'chreeid_signed_in';

    /**
     * 印を付ける。既に付いていれば何もしない。
     *
     * @param Request $request 来ている要求
     * @return void
     */
    public function mark(Request $request): void {
        if ($this->isMarked($request)) return;

        Cookie::queue(cookie()->forever(self::COOKIE, '1', httpOnly: true));
    }

    /**
     * 印を外す。**意図したログアウトのときは必ず外すこと。**
     * 外し忘れると、次の要求が「勝手にログアウトされた」と誤検知される。
     *
     * @return void
     */
    public function forget(): void {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * @param Request $request 来ている要求
     * @return bool 印が付いているか
     */
    public function isMarked(Request $request): bool {
        return $request->cookie(self::COOKIE) !== null;
    }
}
