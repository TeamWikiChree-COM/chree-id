<?php
namespace App\Modules\Identity\Application;

use RuntimeException;

/**
 * 統合が成立しなかったときの合図。
 *
 * 理由コードだけを持ち、利用者に見せる文言は表示側で決める。
 */
class MergeException extends RuntimeException {
    /** 寄せ先と寄せ元が同じアカウント */
    public const SAME_ACCOUNT = 'same_account';

    /** 同じサービスに両方が紐付いていて、渡す sub を決められない */
    public const SAME_SERVICE = 'same_service';

    /**
     * @param string $reason 上記の定数のいずれか
     */
    public function __construct(public readonly string $reason) {
        parent::__construct($reason);
    }
}
