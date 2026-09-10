<?php
namespace App\Modules\Linking\Application;

use RuntimeException;

/**
 * 分離が成立しなかったときの合図。
 *
 * 理由コードだけを持ち、利用者に見せる文言は表示側で決める。
 */
class SplitException extends RuntimeException {
    /** 認証手段を1つも持っていかない。分離した先に誰も入れなくなる */
    public const NO_CREDENTIAL = 'no_credential';

    /** 指定されたアドレスが他の人に使われている */
    public const EMAIL_TAKEN = 'email_taken';

    /** 対象のサービスアカウントが見つからない、または本人のものではない */
    public const NOT_FOUND = 'not_found';

    /**
     * @param string $reason 上記の定数のいずれか
     */
    public function __construct(public readonly string $reason) {
        parent::__construct($reason);
    }
}
