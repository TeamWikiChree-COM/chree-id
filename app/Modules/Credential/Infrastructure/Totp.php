<?php
namespace App\Modules\Credential\Infrastructure;

use InvalidArgumentException;

/**
 * TOTP (RFC 6238) の生成と検証。
 *
 * 認証アプリ側は HMAC-SHA1 / 6桁 / 30秒が事実上の標準なので、それに合わせる。
 */
class Totp {
    /** コードの桁数 */
    private const DIGITS = 6;

    /** 1つのコードが有効な秒数 */
    private const PERIOD = 30;

    /**
     * 端末の時計のずれを許す範囲。前後1つまで受け付ける。
     * 広げるほど盗んだコードを使える時間が延びるので増やさない。
     */
    private const WINDOW = 1;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * 新しい秘密鍵を作る (base32)
     *
     * @return string
     */
    public function generateSecret(): string {
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * 入力されたコードが正しいか。
     *
     * @param string $secret base32 の秘密鍵
     * @param string $code 利用者が入力した数字
     * @param int|null $at 判定に使う時刻。省略時は現在
     * @return bool
     */
    public function verify(string $secret, string $code, ?int $at = null): bool {
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) return false;

        $counter = intdiv($at ?? time(), self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            // 総当たりで桁ごとの一致を測られないよう定数時間で比べる
            if (hash_equals($this->at($secret, $counter + $offset), $code)) return true;
        }

        return false;
    }

    /**
     * 指定したカウンタのコードを作る
     *
     * @param string $secret base32 の秘密鍵
     * @param int $counter 30秒ごとに増える値
     * @return string
     */
    public function at(string $secret, int $counter): string {
        $hash = hash_hmac('sha1', pack('J', $counter), $this->decodeBase32($secret), true);

        // 末尾4ビットが指す位置から4バイト取り出す (RFC 4226 の Dynamic Truncation)
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * 認証アプリに読み込ませる URI (otpauth://)
     *
     * @param string $secret base32 の秘密鍵
     * @param string $accountName 利用者に見える名前
     * @param string $issuer サービス名
     * @return string
     */
    public function provisioningUri(string $secret, string $accountName, string $issuer): string {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);

        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
    }

    /**
     * @param string $secret base32 の文字列
     * @return string
     * @throws InvalidArgumentException base32 として読めない場合
     */
    private function decodeBase32(string $secret): string {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);
            if ($index === false) throw new InvalidArgumentException('base32 として読めません');

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) break;

            // 8ビットずつ取り出しているので必ず 0-255 に収まる
            $binary .= chr(((int) bindec($chunk)) & 0xFF);
        }

        return $binary;
    }
}
