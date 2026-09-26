<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * すべての応答にブラウザ向けの防御ヘッダーを付ける。
 *
 * HSTS は Cloudflare 側で付けるのでここでは出さない。
 */
class SecurityHeaders {
    /** @var array<string, string> */
    private const HEADERS = [
        // 同意画面を他サイトの iframe に埋め込まれると、見えないボタンを押させて承認させられる
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
        // アップロードされた画像を中身から HTML 等と推測させない
        'X-Content-Type-Options' => 'nosniff',
        // 認可コードやトークン入りの URL を遷移先へ漏らさない
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (!$response->headers->has($name)) $response->headers->set($name, $value);
        }

        return $response;
    }
}
