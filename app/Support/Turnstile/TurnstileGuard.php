<?php
namespace App\Support\Turnstile;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * フォームの Turnstile を検証し、通らなければ入力エラーとして止める。
 *
 * ValidationRule として実装すると、Cloudflare に到達できなかった場合の例外が
 * バリデータの内部を通って伝播することになり、制御の流れが追えない。
 * 呼び出し側から明示的に叩く形にしている。
 */
class TurnstileGuard {
    /** Turnstile のウィジェットが送ってくるフィールド名 */
    public const FIELD = 'cf-turnstile-response';

    public function __construct(private readonly TurnstileVerifier $verifier) {}

    /**
     * @param Request $request 検証対象のリクエスト
     * @throws ValidationException 人間と確認できなかった場合、または Cloudflare に到達できなかった場合
     */
    public function check(Request $request): void {
        if (!$this->verifier->isConfigured()) return;

        $token = $request->string(self::FIELD)->toString();

        try {
            $passed = $this->verifier->verify($token, $request->ip());
        } catch (TurnstileUnavailableException $e) {
            // 利用者に落ち度がないので、入力の誤りとは区別する。原因は追える必要があるのでログには残す
            report($e);

            throw ValidationException::withMessages([
                self::FIELD => __('turnstile.unavailable'),
            ])->status(503);
        }

        if ($passed) return;

        throw ValidationException::withMessages([
            self::FIELD => __('turnstile.failed'),
        ]);
    }
}
