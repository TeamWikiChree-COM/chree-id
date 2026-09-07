<?php
namespace App\Modules\Provider\Http;

use App\Modules\Provider\Infrastructure\SigningKey;
use Illuminate\Http\JsonResponse;

/**
 * 署名鍵の公開 (JWKS)
 *
 * RP はここの公開鍵で ID Token を検証する。
 * 鍵をローテーションするときは、古い鍵をしばらく残しておく必要がある。
 */
class JwksController {
    public function __construct(private readonly SigningKey $key) {}

    /**
     * @return JsonResponse
     */
    public function __invoke(): JsonResponse {
        return response()->json(['keys' => [$this->key->toJwk()]]);
    }
}
