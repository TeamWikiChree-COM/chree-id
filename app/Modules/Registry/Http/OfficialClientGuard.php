<?php
namespace App\Modules\Registry\Http;

use App\Modules\Registry\Application\AuthenticateClient;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use App\Support\Api\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * サーバ間 API を叩けるサービスかどうかを確かめる。
 *
 * ブラウザを介さず、利用者の同意も挟まずにアカウントを触る口なので、
 * 公式サービス (`trust=official`) の機密クライアントに限る。
 * 承認済みの第三者に開けると、そのサービスの都合で ChreeID の利用者を水増ししたり、
 * 他所の利用者のパスワードを試したりできてしまう。
 */
class OfficialClientGuard {
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
            return ApiError::make('invalid_client', 'クライアント認証に失敗しました', 401);
        }

        if ($client->trust !== ServiceTrust::OFFICIAL) {
            return ApiError::make('access_denied', 'このサービスはこの操作を行えません', 403);
        }

        return $client;
    }
}
