<?php
namespace App\Modules\Provider\Http;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Provider\Domain\Claims\ScopeRegistry;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OIDC の userinfo エンドポイント。
 *
 * アクセストークンの scope の範囲でだけクレームを返す。
 */
class UserinfoController {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly ResolveSubject $subjects,
        private readonly ScopeRegistry $scopes,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse {
        $bearer = $request->bearerToken();
        if ($bearer === null) return $this->unauthorized(__('oauth.error.token_missing'));

        $token = AccessTokenModel::query()->where('token_hash', hash('sha256', $bearer))->first();
        if ($token === null || !$token->isUsable()) return $this->unauthorized(__('oauth.error.token_invalid'));

        $account = $this->accounts->findById($token->auth_identity_id);
        $client = OAuthClientModel::query()->find($token->client_id);
        if ($account === null || $client === null) return $this->unauthorized(__('oauth.error.token_invalid'));

        // sub は発行時と同じ値でなければ RP 側で突き合わせできない。
        // トークンに控えたサービスアカウントを使う
        $serviceAccount = $token->service_account_id === null
            ? null
            : ServiceAccountModel::query()->find($token->service_account_id);

        $claims = array_merge(
            ['sub' => $serviceAccount === null
                ? $this->subjects->execute($client, $account->id)
                : $this->subjects->forServiceAccount($serviceAccount)],
            $this->scopes->claimsFor($account, $token->scopes()),
        );

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
