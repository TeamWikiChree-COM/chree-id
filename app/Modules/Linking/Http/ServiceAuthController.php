<?php
namespace App\Modules\Linking\Http;

use App\Modules\Linking\Application\ServiceMagicLink;
use App\Modules\Linking\Application\VerifyServiceUserPassword;
use App\Modules\Linking\Domain\ServiceAuthOutcome;
use App\Modules\Registry\Http\EnsureProvisioningClient;
use App\Support\Api\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * サービスが自前のログインフォームのまま、照合だけこちらに任せる口。
 *
 * サービス側のアカウントは見せかけで、実体は ChreeID なので、
 * パスワードはサービスに置かない。ここが唯一の照合場所になる。
 *
 * **平文のパスワードが流れる。** 呼べるのは発行を許したクライアントだけで、
 * かつ呼び出し元に紐付いた利用者しか引けない (VerifyServiceUserPassword)。
 */
class ServiceAuthController {

    private readonly VerifyServiceUserPassword $verify;
    private readonly ServiceMagicLink $magicLinks;

    public function __construct(VerifyServiceUserPassword $verify, ServiceMagicLink $magicLinks) {
        $this->verify = $verify;
        $this->magicLinks = $magicLinks;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function verifyPassword(Request $request): JsonResponse {
        $client = EnsureProvisioningClient::client($request);

        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->verify->execute(
            $client,
            $request->string('email')->trim()->toString(),
            $request->string('password')->toString(),
        );

        // アカウントの有無を出し分けると総当たりで登録済みかを調べられるので、文言を揃える
        if ($result->outcome === ServiceAuthOutcome::INVALID) {
            return ApiError::make('invalid_grant', __('api.password.invalid_grant'), 401);
        }

        if ($result->outcome === ServiceAuthOutcome::SECOND_FACTOR_REQUIRED) {
            return response()->json(['status' => ServiceAuthOutcome::SECOND_FACTOR_REQUIRED->value]);
        }

        return response()->json([
            'status' => ServiceAuthOutcome::OK->value,
            'sub' => $result->sub,
            'service_user_id' => $result->serviceUserId,
        ]);
    }

    /**
     * サービスが自前で出すメールリンクの、一度きりの合図を発行する。
     *
     * **画面もメールもサービスのまま。** 利用者はブラウザを移動しない。
     *
     * アドレスを知らなくても、知っているように振る舞わない。
     * 呼び出し元は結果にかかわらず同じ画面を出すこと。
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function issueMagicLink(Request $request): JsonResponse {
        $client = EnsureProvisioningClient::client($request);

        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $token = $this->magicLinks->issue($client, $request->string('email')->trim()->toString());

        if ($token === null) {
            return ApiError::make('not_applicable', __('api.magic_link.not_applicable'), 404);
        }

        return response()->json(['token' => $token]);
    }

    /**
     * メールリンクの合図を使い切る。
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function consumeMagicLink(Request $request): JsonResponse {
        $client = EnsureProvisioningClient::client($request);

        $request->validate(['token' => ['required', 'string', 'max:128']]);

        $serviceUserId = $this->magicLinks->consume($client, $request->string('token')->toString());

        if ($serviceUserId === null) {
            return ApiError::make('invalid_grant', __('api.magic_link.invalid'), 401);
        }

        return response()->json(['service_user_id' => $serviceUserId]);
    }
}
