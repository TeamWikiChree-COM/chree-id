<?php
namespace App\Modules\Linking\Http;

use App\Modules\Linking\Application\VerifyServiceUserPassword;
use App\Modules\Linking\Domain\ServiceAuthOutcome;
use App\Modules\Registry\Http\OfficialClientGuard;
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
 * **平文のパスワードが流れる。** 呼べるのは公式サービスの機密クライアントだけで、
 * かつ呼び出し元に紐付いた利用者しか引けない (VerifyServiceUserPassword)。
 */
class ServiceAuthController {
    public function __construct(
        private readonly OfficialClientGuard $guard,
        private readonly VerifyServiceUserPassword $verify,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function verifyPassword(Request $request): JsonResponse {
        $client = $this->guard->check($request);
        if ($client instanceof JsonResponse) return $client;

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
            return ApiError::make('invalid_grant', 'メールアドレスまたはパスワードが違います', 401);
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
}
