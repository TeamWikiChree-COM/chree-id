<?php
namespace App\Modules\Credential\Infrastructure\Passkey;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * 発行したチャレンジの預かり所。
 *
 * **セッションには置かない。** options は数KBあり、セッションに入れると保存が壊れて
 * 中身ごと失われる (本番で「パスキーを登録するとログアウトされる」形で踏んだ)。
 * セッションは小さな状態のための場所で、こういう塊を置く場所ではない。
 *
 * 代わりに、ここへ預けて短い引換券だけをセッションに持たせる。券は一度きりで、
 * 使うと消える。使い回せると、同じチャレンジで何度も登録を試せてしまう。
 */
class PasskeyChallenges {
    /** 預かる時間 (分)。端末の生体認証を待つあいだ持てばよい */
    private const MINUTES = 10;

    private const PREFIX = 'passkey.challenge.';

    private readonly PasskeySerializer $serializer;

    public function __construct(PasskeySerializer $serializer) {
        $this->serializer = $serializer;
    }

    /**
     * 預かって引換券を返す。
     *
     * @param PublicKeyCredentialCreationOptions $options 発行したチャレンジ
     * @return string 引換券。セッションに入れるのはこれだけ
     */
    public function remember(PublicKeyCredentialCreationOptions $options): string {
        $handle = Str::random(40);

        Cache::put(
            self::PREFIX . $handle,
            $this->serializer->encodeOptions($options),
            now()->addMinutes(self::MINUTES),
        );

        return $handle;
    }

    /**
     * 引換券と交換に取り出す。**取り出したら消す。**
     *
     * @param mixed $handle セッションに入っていた引換券
     * @return PublicKeyCredentialCreationOptions|null 無い・期限切れ・壊れている場合は null
     */
    public function pull(mixed $handle): ?PublicKeyCredentialCreationOptions {
        if (!is_string($handle) || $handle === '') return null;

        $json = Cache::pull(self::PREFIX . $handle);
        if (!is_string($json)) return null;

        try {
            return $this->serializer->decodeCreationOptions($json);
        } catch (Throwable) {
            // 壊れた預かりものは無かったことにする。呼び出し側が「チャレンジが無い」を返す
            return null;
        }
    }
}
