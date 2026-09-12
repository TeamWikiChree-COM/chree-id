<?php
namespace App\Modules\Backup\Application;

use App\Modules\Backup\Domain\BackupSettings;
use RuntimeException;

/**
 * バックアップの暗号化。
 *
 * **全体を包む。** ModParks は機密の表だけを包んで残りを人が読めるようにしているが、
 * ChreeID は中身がほぼ資格情報 (パスワードハッシュ・TOTP 秘密鍵・使い捨てトークン・
 * クライアントシークレット) なので、平文で残す価値のある表がほとんど無い。
 *
 * 鍵は `.env` の `CHREEID_BACKUP_KEY`。**アプリの鍵 (APP_KEY) と分ける**のは、
 * バックアップを別の場所へ持ち出しても復号できるようにするため。
 */
class BackupCipher {
    public function __construct(private readonly BackupSettings $settings) {}

    /** 認証付き暗号。改竄された控えを黙って戻さないために GCM を使う */
    private const ALGORITHM = 'aes-256-gcm';

    /** GCM の推奨 IV 長 */
    private const IV_BYTES = 12;

    /**
     * 設定されているか。
     *
     * @return bool 鍵があるか
     */
    public function isConfigured(): bool {
        return $this->settings->key() !== null;
    }

    /**
     * @param array<string, mixed> $data 包む中身
     * @return string 保存する本文 (JSON)
     * @throws RuntimeException 鍵が無い、または暗号化に失敗した
     */
    public function seal(array $data): string {
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';

        $cipherText = openssl_encrypt(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            self::ALGORITHM,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($cipherText === false) throw new RuntimeException('バックアップを暗号化できませんでした');

        return json_encode([
            'alg' => self::ALGORITHM,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipherText),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $sealed seal() が返した本文
     * @return array<string, mixed> 元の中身
     * @throws RuntimeException 鍵が違う、形が違う、または改竄されている
     */
    public function open(string $sealed): array {
        /** @var array<string, mixed> $envelope */
        $envelope = json_decode($sealed, true, 512, JSON_THROW_ON_ERROR);

        foreach (['alg', 'iv', 'tag', 'data'] as $field) {
            if (!is_string($envelope[$field] ?? null)) throw new RuntimeException("バックアップの形が違います: {$field}");
        }

        if ($envelope['alg'] !== self::ALGORITHM) {
            throw new RuntimeException('知らない暗号方式です: ' . (string) $envelope['alg']);
        }

        $plain = openssl_decrypt(
            (string) base64_decode((string) $envelope['data'], true),
            self::ALGORITHM,
            $this->key(),
            OPENSSL_RAW_DATA,
            (string) base64_decode((string) $envelope['iv'], true),
            (string) base64_decode((string) $envelope['tag'], true),
        );

        // GCM なので、鍵違いも改竄もここで false になる
        if ($plain === false) throw new RuntimeException('バックアップを復号できませんでした (鍵が違うか、壊れています)');

        /** @var array<string, mixed> $data */
        $data = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @return string 生の鍵 (32 バイト)
     * @throws RuntimeException 鍵が無い、または長さが違う
     */
    private function key(): string {
        $configured = $this->settings->key();
        if ($configured === null) throw new RuntimeException('CHREEID_BACKUP_KEY が設定されていません');

        $key = base64_decode($configured, true);

        // 短い鍵を黙って受けると、弱いまま運用され続ける
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('CHREEID_BACKUP_KEY は base64 で 32 バイトにしてください');
        }

        return $key;
    }
}
