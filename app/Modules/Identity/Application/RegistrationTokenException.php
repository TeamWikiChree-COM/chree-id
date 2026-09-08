<?php
namespace App\Modules\Identity\Application;

use RuntimeException;

/**
 * 登録確認リンクを受け付けられなかったときの例外。
 *
 * 画面に出す文言を理由ごとに変えるため、理由を型ではなく定数で持たせている。
 */
class RegistrationTokenException extends RuntimeException {
    public const NOT_FOUND = 'not_found';
    public const EXPIRED = 'expired';
    public const EMAIL_TAKEN = 'email_taken';

    /**
     * @param string $reason 上記の定数のいずれか
     * @param string $message 内部向けの説明
     */
    private function __construct(public readonly string $reason, string $message) {
        parent::__construct($message);
    }

    /**
     * @return self
     */
    public static function notFound(): self {
        return new self(self::NOT_FOUND, '登録確認トークンが見つかりません');
    }

    /**
     * @return self
     */
    public static function expired(): self {
        return new self(self::EXPIRED, '登録確認トークンの期限が切れています');
    }

    /**
     * @return self
     */
    public static function emailTaken(): self {
        return new self(self::EMAIL_TAKEN, '確認までの間にメールアドレスが使われました');
    }
}
