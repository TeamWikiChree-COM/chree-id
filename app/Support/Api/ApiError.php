<?php
namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

/**
 * サーバ間 API のエラー応答の形。
 *
 * OAuth 2.0 の error / error_description に揃えてある。呼び出し側 (サービスのサーバ) は
 * 既に OIDC のトークンエンドポイントを扱っているので、同じ形なら読み口を増やさずに済む。
 */
final class ApiError {
    /**
     * @param string $error 機械可読なコード
     * @param string $description 人が読む説明
     * @param int $status HTTP ステータス
     * @return JsonResponse
     */
    public static function make(string $error, string $description, int $status): JsonResponse {
        return response()->json(['error' => $error, 'error_description' => $description], $status);
    }
}
