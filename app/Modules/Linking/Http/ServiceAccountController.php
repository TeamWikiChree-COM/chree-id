<?php
namespace App\Modules\Linking\Http;

use App\Http\Controllers\Controller;
use App\Modules\Linking\Application\ChangeServiceAccountPassword;
use App\Modules\Linking\Application\ClaimTickets;
use App\Modules\Linking\Application\DeactivateServiceAccount;
use App\Modules\Linking\Application\DescribeServiceAccount;
use App\Modules\Linking\Application\IssueServiceAccount;
use App\Modules\Linking\Application\ServiceAccountTotp;
use App\Modules\Client\Http\EnsureProvisioningClient;
use App\Support\Api\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * サービスが自分の利用者ぶんの ChreeID を扱う口 (/api/v1/service-accounts/{serviceUserId})。
 *
 * ブラウザを介さないサーバ間通信。利用者の同意なしにアカウントが増えるので、
 * 呼べるのは発行を許したクライアントだけにしている (`can_provision`)。
 * 誰にでも開けると、そのサービスの都合で ChreeID の利用者が水増しされてしまう。
 * その確認はルートに掛けた EnsureProvisioningClient が済ませている。
 *
 * 利用者はサービス側の識別子で指す。ChreeID の sub はサービスごとに違うので、
 * サービスが確実に知っているのはこちらだけ。
 */
class ServiceAccountController extends Controller {
    private readonly IssueServiceAccount $issue;
    private readonly ClaimTickets $tickets;
    private readonly DeactivateServiceAccount $deactivate;
    private readonly DescribeServiceAccount $describe;
    private readonly ChangeServiceAccountPassword $changePassword;
    private readonly ServiceAccountTotp $totp;

    public function __construct(IssueServiceAccount $issue, ClaimTickets $tickets, DeactivateServiceAccount $deactivate, DescribeServiceAccount $describe, ChangeServiceAccountPassword $changePassword, ServiceAccountTotp $totp) {
        $this->issue = $issue;
        $this->tickets = $tickets;
        $this->deactivate = $deactivate;
        $this->describe = $describe;
        $this->changePassword = $changePassword;
        $this->totp = $totp;
    }

    /**
     * 利用者の状態を返す。参照だけで、何も発行しない。
     *
     * サービス側が「移行の導線を出すかどうか」を決めるための口。
     * 入場券の発行で代用すると、画面を出すたびに券が切り替わってしまう。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return JsonResponse
     * @throws ValidationException
     */
    public function show(Request $request, string $serviceUserId): JsonResponse {
        $this->validateFor($request, $serviceUserId);

        $status = $this->describe->execute(EnsureProvisioningClient::client($request), $serviceUserId);
        if ($status === null) return ApiError::make('unknown_service_user', __('api.service_user.unknown'), 404);

        return response()->json($status);
    }

    /**
     * 発行する。同じ識別子で何度呼んでも同じ sub になるので PUT にしている。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update(Request $request, string $serviceUserId): JsonResponse {
        $this->validateFor($request, $serviceUserId, [
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'email_verified' => ['boolean'],
            'display_name' => ['nullable', 'string', 'max:100'],
            // 平文は受け取らない。移行元が保存している bcrypt ハッシュをそのまま渡してもらう
            'password_hash' => ['nullable', 'string', 'max:255'],
            // 既に sub を知っている場合。紐付けだけ作り直したいときに使う
            'sub' => ['nullable', 'string', 'max:64'],
            // 移行元が把握している外部IdPの識別子。"google:123" の形
            'external' => ['array'],
            'external.*' => ['string', 'max:190'],
            // 移行元がメールリンクでログインさせているか
            'magic_link' => ['boolean'],
        ]);

        /** @var list<string> $external */
        $external = $request->input('external', []);

        $sub = $this->issue->execute(
            EnsureProvisioningClient::client($request),
            $serviceUserId,
            $this->optional($request->string('email')->trim()->toString()),
            $request->boolean('email_verified'),
            $this->optional($request->string('display_name')->trim()->toString()),
            $this->optional($request->string('password_hash')->toString()),
            $this->optional($request->string('sub')->toString()),
            $external,
            $request->boolean('magic_link'),
        );

        return response()->json(['sub' => $sub]);
    }

    /**
     * 移行元でアカウントが消えたときに呼んでもらう。
     *
     * 消すのはサービス上の人格だけ。認証主体を道連れにするかは
     * DeactivateServiceAccount が「残っているもの」を見て決める。
     *
     * 畳んだあとにもう一度叩かれると 404 になる。呼び出し元は結果を見ずに
     * 投げっぱなしにしてよい (退会そのものを止めたくないため)。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return Response
     * @throws ValidationException
     */
    public function destroy(Request $request, string $serviceUserId): Response {
        $this->validateFor($request, $serviceUserId);

        $found = $this->deactivate->execute(EnsureProvisioningClient::client($request), $serviceUserId);
        if (!$found) return ApiError::make('unknown_service_user', __('api.service_user.not_found'), 404);

        return response()->noContent();
    }

    /**
     * サービス側で退会した利用者を、サービスに渡した sub で畳む。
     *
     * OIDC でだけ結び付いた利用者は service_user_id を持たないので、こちらで指してもらう。
     *
     * @param Request $request
     * @param string $sub そのサービスに渡した sub
     * @return Response
     */
    public function destroyBySubject(Request $request, string $sub): Response {
        $found = $this->deactivate->bySubject(EnsureProvisioningClient::client($request), $sub);
        if (!$found) return ApiError::make('unknown_service_user', __('api.service_user.not_found'), 404);

        return response()->noContent();
    }

    /**
     * 移行元でパスワードが変えられたときに呼んでもらう。
     *
     * **パスワードの正解を持っているのは ChreeID だけ**にしたいので、
     * サービスが自分の画面で変更を受け付けたら必ず流してもらう。
     * これが無いと、古いパスワードが向こうに残ったまま通り続ける。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return Response
     * @throws ValidationException
     */
    public function updatePassword(Request $request, string $serviceUserId): Response {
        // 平文は受け取らない
        $this->validateFor($request, $serviceUserId, ['password_hash' => ['required', 'string', 'max:255']]);

        $result = $this->changePassword->execute(
            EnsureProvisioningClient::client($request),
            $serviceUserId,
            $request->string('password_hash')->toString(),
        );

        // 断る理由ごとに分ける。潰すと移行元が利用者に何を案内すればよいか分からない
        return match ($result) {
            ChangeServiceAccountPassword::OK => response()->noContent(),
            ChangeServiceAccountPassword::MANAGED => ApiError::make('managed', __('api.password.managed'), 409),
            ChangeServiceAccountPassword::NOT_FOUND => ApiError::make('not_found', __('api.password.not_found'), 404),
            default => ApiError::make('unsupported_hash', __('api.password.unsupported_hash'), 422),
        };
    }

    /**
     * TOTP の秘密鍵を発行する。まだ有効化はしない。
     *
     * サービスは QR を出して、利用者が認証アプリに登録したら enableTotp に秘密鍵とコードを送り返す。
     * 発行しただけでは何も変わらないので、こちらは状態を持たない。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return JsonResponse
     * @throws ValidationException
     */
    public function startTotp(Request $request, string $serviceUserId): JsonResponse {
        $this->validateFor($request, $serviceUserId);

        $result = $this->totp->start(EnsureProvisioningClient::client($request), $serviceUserId);
        if ($result['status'] !== ServiceAccountTotp::OK) return $this->totpError($result['status']);

        return response()->json(['secret' => $result['secret'] ?? null, 'otpauth_uri' => $result['otpauth_uri'] ?? null]);
    }

    /**
     * 認証アプリのコードで確認して TOTP を有効にする。復旧コードの平文はこの応答でしか返さない。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return JsonResponse
     * @throws ValidationException
     */
    public function enableTotp(Request $request, string $serviceUserId): JsonResponse {
        $this->validateFor($request, $serviceUserId, [
            // Totp::generateSecret() の形。違う形を通すと照合のたびに例外になる
            'secret' => ['required', 'string', 'regex:/^[A-Z2-7]{32}$/'],
            'code' => ['required', 'string', 'max:16'],
        ]);

        $result = $this->totp->enable(
            EnsureProvisioningClient::client($request),
            $serviceUserId,
            $request->string('secret')->toString(),
            $request->string('code')->trim()->toString(),
        );

        if ($result['status'] !== ServiceAccountTotp::OK) return $this->totpError($result['status']);

        return response()->json(['recovery_codes' => $result['recovery_codes'] ?? []]);
    }

    /**
     * TOTP をやめる。サービス側のセッションだけで外されないよう、今のコードか復旧コードを求める。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return Response
     * @throws ValidationException
     */
    public function disableTotp(Request $request, string $serviceUserId): Response {
        $this->validateFor($request, $serviceUserId, ['code' => ['required', 'string', 'max:64']]);

        $status = $this->totp->disable(
            EnsureProvisioningClient::client($request),
            $serviceUserId,
            $request->string('code')->trim()->toString(),
        );

        return $status === ServiceAccountTotp::OK ? response()->noContent() : $this->totpError($status);
    }

    /**
     * 復旧コードを作り直す。今のコードか復旧コードを求める。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return JsonResponse
     * @throws ValidationException
     */
    public function regenerateRecoveryCodes(Request $request, string $serviceUserId): JsonResponse {
        $this->validateFor($request, $serviceUserId, ['code' => ['required', 'string', 'max:64']]);

        $result = $this->totp->regenerateRecoveryCodes(
            EnsureProvisioningClient::client($request),
            $serviceUserId,
            $request->string('code')->trim()->toString(),
        );

        if ($result['status'] !== ServiceAccountTotp::OK) return $this->totpError($result['status']);

        return response()->json(['recovery_codes' => $result['recovery_codes'] ?? []]);
    }

    /**
     * 断る理由ごとに分ける。潰すと移行元が利用者に何を案内すればよいか分からない。
     *
     * @param string $status ServiceAccountTotp の定数
     * @return JsonResponse
     */
    private function totpError(string $status): JsonResponse {
        return match ($status) {
            ServiceAccountTotp::MANAGED => ApiError::make('managed', __('api.totp.managed'), 409),
            ServiceAccountTotp::NOT_ENABLED => ApiError::make('not_enabled', __('api.totp.not_enabled'), 409),
            ServiceAccountTotp::INVALID_CODE => ApiError::make('invalid_code', __('api.totp.invalid_code'), 422),
            default => ApiError::make('unknown_service_user', __('api.service_user.unknown'), 404),
        };
    }

    /**
     * 引き取り用の一度きりの URL を発行する。
     *
     * サービス側でログイン中の利用者にだけ渡してもらう前提の URL なので、
     * 誰の分かを言えるのはそのサービス自身だけ。ここでは本人確認をしない。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return JsonResponse
     * @throws ValidationException
     */
    public function storeClaimTicket(Request $request, string $serviceUserId): JsonResponse {
        $this->validateFor($request, $serviceUserId);

        $ticket = $this->tickets->issue(EnsureProvisioningClient::client($request), $serviceUserId);

        if ($ticket === null) return ApiError::make('unknown_service_user', __('api.service_user.unknown'), 404);
        if ($ticket->link->isClaimed()) return ApiError::make('already_claimed', __('api.claim.already_claimed'), 409);

        return response()->json([
            'claim_url' => url("/claim/{$ticket->token}"),
            'expires_at' => $ticket->expiresAt->toIso8601String(),
        ], 201);
    }

    /**
     * パスの識別子も本文の項目と同じ規則で検証する。長すぎる識別子を 404 ではなく 422 で断るため。
     *
     * @param Request $request
     * @param string $serviceUserId サービス側での利用者の識別子
     * @param array<string, list<string>> $rules 操作に固有の規則
     * @throws ValidationException
     */
    private function validateFor(Request $request, string $serviceUserId, array $rules = []): void {
        $request->merge(['service_user_id' => $serviceUserId]);

        $request->validate(['service_user_id' => ['required', 'string', 'max:190']] + $rules);
    }

    /**
     * `?:` だと "0" まで null になるので、空文字だけを落とす。
     *
     * @param string $value
     * @return string|null 空なら null
     */
    private function optional(string $value): ?string {
        return $value === '' ? null : $value;
    }

}
