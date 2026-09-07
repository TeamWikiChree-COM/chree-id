<?php
namespace App\Modules\Provider\Http;

use Illuminate\Http\JsonResponse;

/**
 * OIDC のディスカバリ
 *
 * RP はここを読んで各エンドポイントを自動設定する。仕様で場所が決まっているので
 * パスを変えてはいけない (OpenID Connect Discovery 1.0)。
 */
class DiscoveryController {
    /**
     * @return JsonResponse
     */
    public function __invoke(): JsonResponse {
        $issuer = config('chreeid.issuer');
        $issuer = rtrim(is_string($issuer) ? $issuer : '', '/');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/oauth/authorize',
            'token_endpoint' => $issuer . '/oauth/token',
            'userinfo_endpoint' => $issuer . '/oauth/userinfo',
            'jwks_uri' => $issuer . '/oauth/jwks',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['pairwise', 'public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'name', 'email', 'email_verified'],
        ]);
    }
}
