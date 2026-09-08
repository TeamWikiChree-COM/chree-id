<?php
namespace App\Support\Turnstile;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile のトークンを検証する。
 *
 * 鍵が設定されていない環境では検証そのものを行わない。
 * ローカルや CI に本番の鍵を配らずに済ませるための割り切りで、
 * 有効/無効は isConfigured() で明示的に問い合わせること。
 */
class TurnstileVerifier {
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** siteverify の応答を待つ秒数。ここで詰まると登録画面ごと止まるので短くする */
    private const TIMEOUT_SECONDS = 5;

    /**
     * 検証が有効か (サイトキーとシークレットの両方が入っているか)
     *
     * @return bool
     */
    public function isConfigured(): bool {
        return $this->siteKey() !== null && $this->secretKey() !== null;
    }

    /**
     * 画面に埋め込むサイトキー
     *
     * @return string|null 未設定なら null
     */
    public function siteKey(): ?string {
        return $this->stringConfig('site_key');
    }

    /**
     * トークンを検証する。
     *
     * @param string|null $token フォームの cf-turnstile-response
     * @param string|null $ip 送信元 IP。Cloudflare 側の判定材料になる
     * @return bool 未設定の環境では常に true
     * @throws TurnstileUnavailableException Cloudflare に到達できなかった場合
     */
    public function verify(?string $token, ?string $ip = null): bool {
        if (!$this->isConfigured()) return true;
        if ($token === null || $token === '') return false;

        $payload = ['secret' => $this->secretKey(), 'response' => $token];
        if ($ip !== null) $payload['remoteip'] = $ip;

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::VERIFY_URL, $payload);
        } catch (Throwable $e) {
            throw new TurnstileUnavailableException('Turnstile の検証に到達できませんでした', 0, $e);
        }

        if (!$response->successful()) {
            throw new TurnstileUnavailableException("Turnstile が想定外の応答を返しました: {$response->status()}");
        }

        return $response->json('success') === true;
    }

    /**
     * @return string|null
     */
    private function secretKey(): ?string {
        return $this->stringConfig('secret_key');
    }

    /**
     * @param string $name chreeid.turnstile 配下のキー名
     * @return string|null 空文字も未設定として扱う
     */
    private function stringConfig(string $name): ?string {
        $value = config("chreeid.turnstile.{$name}");
        if (!is_string($value) || $value === '') return null;

        return $value;
    }
}
