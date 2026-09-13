<?php
namespace App\Modules\ApiDocs\Http;

use App\Modules\ApiDocs\Application\OpenApiSpec;
use Illuminate\Http\JsonResponse;

/**
 * OpenAPI 仕様 (/api/v1/openapi.json) を配る。認証は要らない。
 */
class OpenApiController {
    private readonly OpenApiSpec $spec;

    public function __construct(OpenApiSpec $spec) {
        $this->spec = $spec;
    }

    /**
     * @return JsonResponse
     */
    public function __invoke(): JsonResponse {
        $issuer = config('chreeid.issuer');

        return response()->json(
            $this->spec->build(is_string($issuer) ? $issuer : ''),
            200,
            [],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
