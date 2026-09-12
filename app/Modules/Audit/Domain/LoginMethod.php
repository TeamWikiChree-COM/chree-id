<?php
namespace App\Modules\Audit\Domain;

// 履歴に残すログインの手口

// 大半は CredentialType と同じ値だが、こちらは「どう入ったか」の記録であって
// 認証手段そのものではない。登録や引き取りのように、対応する Credential が
// 無い経路もここに並ぶ。
enum LoginMethod: string {
    case PASSWORD = 'password';
    case MAGIC_LINK = 'magic_link';
    case TOTP = 'totp';
    case RECOVERY_CODE = 'recovery_code';
    case PASSKEY = 'passkey';
    case OAUTH = 'oauth';
    case REGISTRATION = 'registration'; // 登録の完了と同時に入った
    case CLAIM = 'claim'; // サービスが発行したアカウントの引き取り

    /**
     * 連携先まで含めた記録用の値にする ('oauth:google')。
     *
     * @param string|null $provider プロバイダ名。無ければ種別だけ
     * @return string
     */
    public function with(?string $provider): string {
        return $provider === null || $provider === '' ? $this->value : "{$this->value}:{$provider}";
    }
}
