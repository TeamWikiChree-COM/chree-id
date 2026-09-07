<?php
namespace App\Modules\Provider\Domain;

use RuntimeException;

/**
 * 認可リクエストの不備。
 *
 * redirect_uri を確認できない段階のエラーは RP へ返してはいけない。
 * 未確認のURLへ飛ばすとオープンリダイレクタになるため、その場合は画面に出す。
 */
class AuthorizeError extends RuntimeException {
    public function __construct(
        public readonly string $error,
        public readonly string $reason,
        public readonly bool $canRedirect,
    ) {
        parent::__construct($reason);
    }

    /**
     * RP へリダイレクトして返してよいエラー (redirect_uri は確認済み)
     *
     * @param string $error OAuth のエラーコード
     * @param string $reason 画面やログ向けの説明
     * @return self
     */
    public static function redirectable(string $error, string $reason): self {
        return new self($error, $reason, true);
    }

    /**
     * RP へ返してはいけないエラー (client_id や redirect_uri が信用できない)
     *
     * @param string $error OAuth のエラーコード
     * @param string $reason 画面やログ向けの説明
     * @return self
     */
    public static function fatal(string $error, string $reason): self {
        return new self($error, $reason, false);
    }
}
