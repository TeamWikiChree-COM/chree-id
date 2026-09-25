<?php
namespace App\Modules\Identity\Application;

use RuntimeException;

/**
 * 追加のメールアドレスを扱えなかったときの合図。
 *
 * 理由コードだけを持ち、利用者に見せる文言は表示側で決める。
 */
class AccountEmailException extends RuntimeException {
    /** ユーザーアカウントではない。サービスアカウントは追加アドレスを持たない */
    public const NOT_USER_ACCOUNT = 'not_user_account';

    /** 主アドレスか追加アドレスとして既に登録されている */
    public const ALREADY_REGISTERED = 'already_registered';

    /** このアカウントのアドレスではない */
    public const NOT_FOUND = 'not_found';

    /** 確認が済んでいない */
    public const NOT_VERIFIED = 'not_verified';

    /** 他のアカウントが主アドレスとして使っている */
    public const TAKEN = 'taken';

    /** 本人のものと確かめたアドレス (と、その「+」付き版) ではない */
    public const NOT_ASSIGNABLE = 'not_assignable';

    /**
     * @param string $reason 上記の定数のいずれか
     */
    public function __construct(public readonly string $reason) {
        parent::__construct($reason);
    }
}
