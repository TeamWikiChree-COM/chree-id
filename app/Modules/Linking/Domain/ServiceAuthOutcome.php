<?php
namespace App\Modules\Linking\Domain;

/**
 * サービス経由のパスワード照会の結果。
 *
 * 「認証できたか」の2値にしないのは、2FA を有効にしている利用者が居るため。
 * パスワードが正しくても認証は成立しておらず、成立させるには追加の要素が要る。
 * その要素をサーバ間 API では受け取れないので、状態として呼び出し元に返す。
 */
enum ServiceAuthOutcome: string {
    /** 認証が成立した。そのままログインさせてよい */
    case OK = 'ok';

    /** アドレスかパスワードが違う。どちらが違うかは返さない */
    case INVALID = 'invalid';

    /** パスワードは正しいが2要素目が要る。ブラウザを介す OIDC へ寄せてもらう */
    case SECOND_FACTOR_REQUIRED = 'second_factor_required';
}
