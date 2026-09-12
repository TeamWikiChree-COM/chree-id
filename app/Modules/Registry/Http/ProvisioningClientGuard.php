<?php
namespace App\Modules\Registry\Http;

use App\Modules\Registry\Application\AuthenticateClient;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use App\Support\Api\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * サービスアカウントを扱えるクライアントかどうかを確かめる。
 *
 * ブラウザを介さず、利用者の同意も挟まずにアカウントを触る口なので、
 * **明示的に許したクライアント (`can_provision`) の機密クライアントに限る。**
 * 誰にでも開けると、そのサービスの都合で ChreeID の利用者を水増ししたり、
 * 他所の利用者のパスワードを試したりできてしまう。
 *
 * `trust` では判定しない。あれは外部から見た信頼の表明であって権限ではない。
 */
class ProvisioningClientGuard {
    public function __construct(private readonly AuthenticateClient $clients) {}

    /**
     * 通れば呼び出し元のクライアント、通らなければそのまま返すべき応答を返す。
     *
     * @param Request $request
     * @return OAuthClientModel|JsonResponse
     */
    public function check(Request $request): OAuthClientModel|JsonResponse {
        $client = $this->clients->execute($request);

        if ($client === null || !$client->is_confidential) {
            return ApiError::make('invalid_client', __('api.client.authentication_failed'), 401);
        }

        if (!$client->can_provision) {
            return ApiError::make('access_denied', __('api.client.provision_denied'), 403);
        }

        return $client;
    }
}
