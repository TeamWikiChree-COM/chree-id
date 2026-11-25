<?php
namespace App\Modules\Linking\Http;

use App\Http\Controllers\Controller;
use App\Modules\Linking\Application\ServiceMagicLink;
use App\Modules\Linking\Application\VerifyServiceUserPassword;
use App\Modules\Linking\Application\VerifyServiceUserSecondFactor;
use App\Modules\Linking\Domain\ServiceAuthOutcome;
use App\Modules\Client\Http\EnsureProvisioningClient;
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
class ServiceAuthController extends Controller {
    private readonly VerifyServiceUserPassword $verify;
    private readonly ServiceMagicLink $magicLinks;
    private readonly VerifyServiceUserSecondFactor $secondFactor;

    public function __construct(VerifyServiceUserPassword $verify, ServiceMagicLink $magicLinks, VerifyServiceUserSecondFactor $secondFactor) {
        $this->verify = $verify;
        $this->magicLinks = $magicLinks;
        $this->secondFactor = $secondFactor;
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
            return response()->json([
                'status' => ServiceAuthOutcome::SECOND_FACTOR_REQUIRED->value,
                'challenge' => $result->challenge,
                'expires_in' => VerifyServiceUserPassword::CHALLENGE_MINUTES * 60,
            ]);
        }

        return $this->authenticated($result->sub, $result->serviceUserId);
    }

    /**
     * パスワードの照合で返した合図と、2要素目のコードを受け取る。
     *
     * 画面はサービスのまま。合図は一度きりなので、コードを間違えたらパスワードからやり直してもらう。
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function verifySecondFactor(Request $request): JsonResponse {
        $client = EnsureProvisioningClient::client($request);

        $request->validate([
            'challenge' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->secondFactor->execute(
            $client,
            $request->string('challenge')->toString(),
            $request->string('code')->trim()->toString(),
        );

        if ($result->outcome !== ServiceAuthOutcome::OK) {
            return ApiError::make('invalid_grant', __('api.second_factor.invalid_grant'), 401);
        }

        return $this->authenticated($result->sub, $result->serviceUserId);
    }

    /**
     * @param string|null $sub サービスへ渡す sub
     * @param string|null $serviceUserId サービス側での利用者の識別子
     * @return JsonResponse
     */
    private function authenticated(?string $sub, ?string $serviceUserId): JsonResponse {
        return response()->json([
            'status' => ServiceAuthOutcome::OK->value,
            'sub' => $sub,
            'service_user_id' => $serviceUserId,
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
