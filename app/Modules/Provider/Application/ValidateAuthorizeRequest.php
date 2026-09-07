<?php
namespace App\Modules\Provider\Application;

use App\Modules\Provider\Domain\AuthorizeError;
use App\Modules\Provider\Domain\AuthorizeRequest;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Http\Request;

/**
 * /authorize のパラメータを検証する。
 *
 * client_id と redirect_uri が確認できるまでは RP へリダイレクトしない。
 * 未確認のURLへ飛ばすとオープンリダイレクタになる。
 */
class ValidateAuthorizeRequest {
    /**
     * @param Request $request
     * @return AuthorizeRequest
     * @throws AuthorizeError 検証に失敗した場合
     */
    public function execute(Request $request): AuthorizeRequest {
        $client = $this->findClient($request->string('client_id')->toString());
        $redirectUri = $this->checkRedirectUri($client, $request->string('redirect_uri')->toString());

        if ($request->string('response_type')->toString() !== 'code') {
            throw AuthorizeError::redirectable('unsupported_response_type', 'response_type は code のみ対応しています');
        }

        $scopes = $this->checkScopes($client, $request->string('scope')->toString());
        $challenge = $this->checkPkce($client, $request);

        return new AuthorizeRequest(
            $client,
            $redirectUri,
            $scopes,
            $this->optional($request, 'state'),
            $this->optional($request, 'nonce'),
            $challenge['challenge'],
            $challenge['method'],
        );
    }

    /**
     * @param string $clientId
     * @return OAuthClientModel
     * @throws AuthorizeError
     */
    private function findClient(string $clientId): OAuthClientModel {
        $client = OAuthClientModel::query()->find($clientId);

        if ($client === null) throw AuthorizeError::fatal('invalid_client', 'client_id が登録されていません');
        if (!$client->trust->isUsable()) throw AuthorizeError::fatal('unauthorized_client', 'このサービスは利用を停止しています');

        return $client;
    }

    /**
     * @param OAuthClientModel $client
     * @param string $redirectUri
     * @return string
     * @throws AuthorizeError
     */
    private function checkRedirectUri(OAuthClientModel $client, string $redirectUri): string {
        if ($redirectUri === '') throw AuthorizeError::fatal('invalid_request', 'redirect_uri が指定されていません');
        if (!$client->allowsRedirectUri($redirectUri)) {
            throw AuthorizeError::fatal('invalid_request', 'redirect_uri が登録されていません');
        }

        return $redirectUri;
    }

    /**
     * @param OAuthClientModel $client
     * @param string $scope
     * @return list<string>
     * @throws AuthorizeError
     */
    private function checkScopes(OAuthClientModel $client, string $scope): array {
        $scopes = array_values(array_filter(explode(' ', $scope), static fn (string $s): bool => $s !== ''));

        if (!in_array('openid', $scopes, true)) {
            throw AuthorizeError::redirectable('invalid_scope', 'scope に openid が必要です');
        }
        if (!$client->allowsScopes($scopes)) {
            throw AuthorizeError::redirectable('invalid_scope', '許可されていない scope が含まれています');
        }

        return $scopes;
    }

    /**
     * @param OAuthClientModel $client
     * @param Request $request
     * @return array{challenge: string|null, method: string|null}
     * @throws AuthorizeError
     */
    private function checkPkce(OAuthClientModel $client, Request $request): array {
        $challenge = $this->optional($request, 'code_challenge');
        $method = $this->optional($request, 'code_challenge_method');

        if ($challenge === null) {
            // public クライアントは秘密を持てないので PKCE を省略させない
            if ($client->requiresPkce()) {
                throw AuthorizeError::redirectable('invalid_request', 'このクライアントは code_challenge が必須です');
            }

            return ['challenge' => null, 'method' => null];
        }

        // plain は攻撃者が challenge をそのまま送れるので受け付けない
        if ($method !== 'S256') {
            throw AuthorizeError::redirectable('invalid_request', 'code_challenge_method は S256 のみ対応しています');
        }

        return ['challenge' => $challenge, 'method' => $method];
    }

    /**
     * @param Request $request
     * @param string $key
     * @return string|null 空文字は未指定として扱う
     */
    private function optional(Request $request, string $key): ?string {
        $value = $request->string($key)->toString();

        return $value === '' ? null : $value;
    }
}
