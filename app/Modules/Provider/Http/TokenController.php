<?php
namespace App\Modules\Provider\Http;

use App\Modules\Client\Application\AuthenticateClient;
use App\Modules\Provider\Application\ExchangeAuthCode;
use App\Modules\Provider\Application\TokenException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OIDC のトークンエンドポイント。
 *
 * 認可コードを ID Token とアクセストークンに交換する。
 */
class TokenController {
    public function __construct(
        private readonly AuthenticateClient $clients,
        private readonly ExchangeAuthCode $exchange,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse {
        if ($request->string('grant_type')->toString() !== 'authorization_code') {
            return $this->error('unsupported_grant_type', __('oauth.error.grant_type_unsupported'));
        }

        $client = $this->clients->execute($request);
        if ($client === null) return $this->error('invalid_client', __('oauth.error.client_authentication_failed'), 401);

        try {
            $tokens = $this->exchange->execute(
                $client,
                $request->string('code')->toString(),
                $request->string('redirect_uri')->toString(),
                $request->string('code_verifier')->toString(),
            );
        } catch (TokenException $e) {
            return $this->error($e->error, $e->getMessage());
        }

        return response()->json([
            'access_token' => $tokens->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokens->expiresIn,
            'id_token' => $tokens->idToken,
            'scope' => $tokens->scope,
        ]);
    }

    /**
     * @param string $error OAuth のエラーコード
     * @param string $description 説明
     * @param int $status HTTP ステータス
     * @return JsonResponse
     */
    private function error(string $error, string $description, int $status = 400): JsonResponse {
        return response()->json(['error' => $error, 'error_description' => $description], $status);
    }
}
