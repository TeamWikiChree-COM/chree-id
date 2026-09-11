<?php
namespace App\Modules\Identity\Domain;

/**
 * メールアドレス変更の申し込みがどう扱われたか。
 *
 * **断る理由を1つに潰さない。** 「他人が使っている」と「今と同じ」では
 * 利用者がすべきことが違うので、画面に出す文言も分ける必要がある。
 */
enum EmailChangeResult {
    /** 確認メールを送った */
    case SENT;

    /** 今のアドレスと同じ。変えるものが無いので送らない */
    case SAME_AS_CURRENT;

    /** 他のアカウントが使っている */
    case TAKEN;

    /** アカウントが見つからない */
    case UNKNOWN_ACCOUNT;
}
