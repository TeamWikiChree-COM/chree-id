<?php
namespace App\Modules\Linking\Application;

use RuntimeException;

/**
 * 引き取りが成立しなかったときの合図。
 *
 * 理由コードだけを持ち、利用者に見せる文言は表示側で決める。
 */
class ClaimException extends RuntimeException {
    /** 券が無い・期限切れ・使用済み */
    public const INVALID_TICKET = 'invalid_ticket';

    /** 既に本人のアカウントになっている */
    public const ALREADY_CLAIMED = 'already_claimed';

    /** 指定されたアドレスが他の人に使われている */
    public const EMAIL_TAKEN = 'email_taken';

    /**
     * @param string $reason 上記の定数のいずれか
     */
    public function __construct(public readonly string $reason) {
        parent::__construct($reason);
    }
}
