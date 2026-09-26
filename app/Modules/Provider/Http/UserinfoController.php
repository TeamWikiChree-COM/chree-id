<?php
namespace App\Modules\Provider\Http;

use App\Modules\Provider\Application\ResolveUserinfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OIDC の userinfo エンドポイント。
 *
 * アクセストークンの scope の範囲でだけクレームを返す。
 */
class UserinfoController {
    public function __construct(
        private readonly ResolveUserinfo $userinfo,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse {
        $bearer = $request->bearerToken();
        if ($bearer === null) return $this->unauthorized(__('oauth.error.token_missing'));

        $claims = $this->userinfo->execute($bearer);
        if ($claims === null) return $this->unauthorized(__('oauth.error.token_invalid'));

        return response()->json($claims);
    }

    /**
     * @param string $description 説明
     * @return JsonResponse
     */
    private function unauthorized(string $description): JsonResponse {
        return response()
            ->json(['error' => 'invalid_token', 'error_description' => $description], 401)
            ->header('WWW-Authenticate', 'Bearer error="invalid_token"');
    }
}
