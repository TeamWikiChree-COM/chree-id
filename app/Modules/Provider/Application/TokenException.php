<?php
namespace App\Modules\Provider\Application;

use RuntimeException;

/**
 * トークンエンドポイントで返す OAuth のエラー。
 *
 * 原因ごとに説明を分けて返す。1つに潰すと、つなぐ側がどこを直せばよいか分からない。
 */
final class TokenException extends RuntimeException {
    /** OAuth のエラーコード (invalid_grant など) */
    public readonly string $error;

    /**
     * @param string $error OAuth のエラーコード
     * @param string $description 利用者に返す説明
     */
    public function __construct(string $error, string $description) {
        parent::__construct($description);
        $this->error = $error;
    }

    /**
     * @param string $key lang/server の oauth.error.* のキー (接頭辞なし)
     * @return self
     */
    public static function invalidGrant(string $key): self {
        return new self('invalid_grant', __("oauth.error.{$key}"));
    }
}
