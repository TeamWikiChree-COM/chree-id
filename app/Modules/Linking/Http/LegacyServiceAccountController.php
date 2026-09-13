<?php
namespace App\Modules\Linking\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * 旧経路 (POST /api/v1/service-accounts/status など、識別子を本文で送る形)。
 *
 * DokuFarm がこの形で呼んでおり、ステータスも 200 ちょうどで判定しているので、
 * 形と応答をそのまま保つ。処理は ServiceAccountController に任せ、ここは形の読み替えだけ。
 * 呼び出し元が移り終えたら消す (NEW-REST.md)。
 *
 * @deprecated /api/v1/service-accounts/{serviceUserId} を使う
 */
class LegacyServiceAccountController {
    private readonly ServiceAccountController $rest;

    public function __construct(ServiceAccountController $rest) {
        $this->rest = $rest;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse {
        return $this->rest->update($request, $this->serviceUserId($request));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function status(Request $request): JsonResponse {
        return $this->rest->show($request, $this->serviceUserId($request));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function claimTicket(Request $request): JsonResponse {
        $response = $this->rest->storeClaimTicket($request, $this->serviceUserId($request));
        if ($response->getStatusCode() !== 201) return $response;

        return $response->setStatusCode(200);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ValidationException
     */
    public function changePassword(Request $request): Response {
        return $this->ok($this->rest->updatePassword($request, $this->serviceUserId($request)));
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ValidationException
     */
    public function deactivate(Request $request): Response {
        return $this->ok($this->rest->destroy($request, $this->serviceUserId($request)));
    }

    /**
     * 空でもそのまま渡す。required の検証で 422 になる (旧経路と同じ)。
     *
     * @param Request $request
     * @return string
     */
    private function serviceUserId(Request $request): string {
        return $request->string('service_user_id')->toString();
    }

    /**
     * 旧経路は成功を 200 `{"ok": true}` で返していた。
     *
     * @param Response $response
     * @return Response
     */
    private function ok(Response $response): Response {
        if ($response->getStatusCode() !== 204) return $response;

        return response()->json(['ok' => true]);
    }
}
