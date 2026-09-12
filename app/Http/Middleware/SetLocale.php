<?php

namespace App\Http\Middleware;

use App\Support\Locale\LocaleNegotiator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * その要求を返す言語を決める。
 *
 * **HandleInertiaRequests より先に通す必要がある。** 共有 props の locale と
 * バリデーションの文言が、決まる前の言語で組み立てられてしまうため。
 */
class SetLocale {
    public function __construct(private readonly LocaleNegotiator $negotiator) {}

    /**
     * @param Request $request 来ている要求
     * @param Closure(Request): Response $next 次の処理
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        app()->setLocale($this->negotiator->resolve($request));

        return $next($request);
    }
}
