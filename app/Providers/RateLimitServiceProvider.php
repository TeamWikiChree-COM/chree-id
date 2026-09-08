<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * 認証まわりのレート制限。
 *
 * Turnstile が未設定の環境でも最低限の歯止めが要るので、こちらは常に効かせる。
 * IP だけで数えるとメール総当たりを見逃すため、メールアドレスとの組でも数える。
 */
class RateLimitServiceProvider extends ServiceProvider {
    /**
     * @return void
     */
    public function boot(): void {
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by($this->emailKey($request)),
            Limit::perMinute(20)->by($request->ip() ?? 'unknown'),
        ]);

        // 登録は1通ごとにメールが飛ぶので、ログインより厳しくする
        RateLimiter::for('register', fn (Request $request): array => [
            Limit::perHour(5)->by($this->emailKey($request)),
            Limit::perHour(10)->by($request->ip() ?? 'unknown'),
        ]);

        // 6桁の TOTP は総当たりが現実的な桁数なので、ここは特に絞る
        RateLimiter::for('challenge', fn (Request $request): Limit =>
            Limit::perMinute(5)->by($request->session()->getId()));

        // 確認リンクは総当たりされうるが、正規の利用者が何度も開くことはない
        RateLimiter::for('verify', fn (Request $request): Limit =>
            Limit::perMinute(10)->by($request->ip() ?? 'unknown'));
    }

    /**
     * @param Request $request
     * @return string メールアドレスと IP の組。アドレスを変えるだけでは逃れられないようにする
     */
    private function emailKey(Request $request): string {
        $email = mb_strtolower($request->string('email')->trim()->toString());

        return $email . '|' . ($request->ip() ?? 'unknown');
    }
}
