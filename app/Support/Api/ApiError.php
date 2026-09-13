<?php
namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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

    /**
     * 入力エラー。どの項目がなぜ駄目かは `errors` に項目ごとに載せる。
     *
     * @param ValidationException $e
     * @return JsonResponse
     */
    public static function invalidRequest(ValidationException $e): JsonResponse {
        return response()->json([
            'error' => 'invalid_request',
            'error_description' => $e->getMessage(),
            'errors' => $e->errors(),
        ], $e->status);
    }
}
