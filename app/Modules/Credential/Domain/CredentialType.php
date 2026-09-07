<?php
namespace App\Modules\Credential\Domain;

// 認証情報の種類

// 既存の認証方法と新規認証方法を整理するのに使うやつ。
// 一覧は RESEARCH.md 3.1 を見ればわかる。
enum CredentialType: string {
    case PASSWORD = 'password'; // パスワードログイン
    case MAGIC_LINK = 'magic_link'; // マジックリンク
    case TOTP = 'totp'; // 2段階認証 (TOTP)
    case PASSKEY = 'passkey'; // パスキー認証
    case OAUTH = 'oauth'; // OAuth認証
    case RECOVERY_CODE = 'recovery_code'; // TOTPの端末を失くしたときの復旧用
}
