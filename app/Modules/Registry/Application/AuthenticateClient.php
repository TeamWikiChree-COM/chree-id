<?php
namespace App\Modules\Registry\Application;

use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Http\Request;

/**
 * リクエストの送り主がどのサービスかを確かめる。
 *
 * トークンエンドポイントとサーバ間 API の両方が同じ判定を要るので、
 * 片方だけ緩むことがないよう1か所に置く。
 */
class AuthenticateClient {
    /**
     * @param Request $request
     * @return OAuthClientModel|null 確かめられなければ null
     */
    public function execute(Request $request): ?OAuthClientModel {
        [$clientId, $secret] = $this->credentials($request);

        $client = $clientId === '' ? null : OAuthClientModel::query()->find($clientId);
        if ($client === null || !$client->trust->isUsable()) return null;

        // public クライアントは秘密を持てない。守るのは PKCE の仕事
        if (!$client->is_confidential) return $client;
        if ($client->secret_hash === null || $secret === null) return null;

        // 突き合わせは定数時間で行う
        return hash_equals($client->secret_hash, hash('sha256', $secret)) ? $client : null;
    }

    /**
     * Basic 認証とフォーム値の両方を受け付ける。
     *
     * @param Request $request
     * @return array{0: string, 1: string|null}
     */
    private function credentials(Request $request): array {
        $user = $request->getUser();
        if (is_string($user) && $user !== '') return [$user, $request->getPassword()];

        $secret = $request->string('client_secret')->toString();

        return [$request->string('client_id')->toString(), $secret === '' ? null : $secret];
    }
}
