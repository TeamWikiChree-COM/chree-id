<?php
namespace App\Modules\Provider\Domain;

/**
 * サービスへのサインインで、次に何をするか。
 */
enum SignInStepKind {
    /** ChreeID にログインしていない */
    case LOGIN;

    /** 引き取り前のサービスアカウントで、発行元以外のサービスへ入ろうとしている */
    case UNCLAIMED;

    /** 同意画面を出す */
    case CONSENT;

    /** 同じサービスにサービスアカウントが複数あり、どれで入るか選ばせる */
    case CHOOSE_ACCOUNT;

    /** 入ってよい。サービスアカウントが決まった */
    case GRANTED;
}
