<?php
namespace App\Modules\ApiDocs\Http;

use Illuminate\Contracts\View\View;

/**
 * API ドキュメントの画面 (/api-docs/v1)。認証は要らない。
 *
 * 画面は Scalar に任せ、こちらは仕様の URL を渡すだけにする。
 * 仕様は OpenApiSpec が組み立てるので、この画面に説明を書き足さないこと。
 */
class ApiDocsController {
    /** 仕様を出している版 */
    private const VERSIONS = ['v1'];

    /**
     * @param string $version URL の版 ("v1" など)
     * @return View
     */
    public function __invoke(string $version): View {
        abort_unless(in_array($version, self::VERSIONS, true), 404);

        return view('api-docs', [
            'version' => $version,
            'specUrl' => url("/api/{$version}/openapi.json"),
        ]);
    }
}
