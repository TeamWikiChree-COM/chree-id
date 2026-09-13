<?php
namespace App\Modules\Registry\Http;

use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * サーバ間 API の入口で、呼び出し元が発行を許されたクライアントかを確かめる。
 *
 * 各アクションの先頭で毎回確かめる書き方だと、足したアクションで書き忘れても
 * 素通りしてしまう。ルートのグループに掛けて、書き忘れようがないようにする。
 */
class EnsureProvisioningClient {
    /** 確かめたクライアントを載せておく属性名 */
    private const ATTRIBUTE = 'chreeid.provisioning_client';

    private readonly ProvisioningClientGuard $guard;

    public function __construct(ProvisioningClientGuard $guard) {
        $this->guard = $guard;
    }

    /**
     * @param Request $request 来ている要求
     * @param Closure(Request): Response $next 次の処理
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        $client = $this->guard->check($request);
        if ($client instanceof JsonResponse) return $client;

        $request->attributes->set(self::ATTRIBUTE, $client);

        return $next($request);
    }

    /**
     * このミドルウェアを通った要求から、呼び出し元のクライアントを取り出す。
     *
     * @param Request $request 来ている要求
     * @return OAuthClientModel
     * @throws LogicException ミドルウェアを掛け忘れたルートから呼ばれた
     */
    public static function client(Request $request): OAuthClientModel {
        $client = $request->attributes->get(self::ATTRIBUTE);
        if ($client instanceof OAuthClientModel) return $client;

        throw new LogicException(self::class . ' が掛かっていないルートです');
    }
}
