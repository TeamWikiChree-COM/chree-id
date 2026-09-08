<?php
namespace App\Modules\Identity\Application;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\PendingRegistrationModel;
use App\Modules\Identity\Mail\RegistrationExistsMail;
use App\Modules\Identity\Mail\VerifyRegistrationMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * 登録の申し込みを受け付け、確認メールを送る。
 *
 * アカウントはここでは作らない。メールのリンクを踏んだ時点で CompleteRegistration が作る。
 * 既存アドレスかどうかで呼び出し側への戻り値を変えないのは、
 * 画面の応答からアドレスの存在を推測させないため。
 */
class StartRegistration {
    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly SetPassword $setPassword,
    ) {}

    /**
     * @param string $email メールアドレス
     * @param string|null $displayName 表示名。未入力なら null
     * @param string $password 平文パスワード
     * @return void
     */
    public function execute(string $email, ?string $displayName, string $password): void {
        // 既に持っている人には、新規登録ではなくログインへの案内を送る
        if ($this->accounts->findByEmail($email) !== null) {
            Mail::to($email)->send(new RegistrationExistsMail(url('/login')));

            return;
        }

        $token = $this->issue($email, $displayName, $password);

        Mail::to($email)->send(new VerifyRegistrationMail(url("/register/verify/{$token}"), $this->ttlMinutes()));
    }

    /**
     * 申し込みを保存し、メールに載せる平文トークンを返す。
     *
     * @param string $email メールアドレス
     * @param string|null $displayName 表示名
     * @param string $password 平文パスワード
     * @return string 平文トークン
     */
    private function issue(string $email, ?string $displayName, string $password): string {
        // 同じアドレスの古い申し込みは捨てる。最後に送ったリンクだけを有効にする
        PendingRegistrationModel::query()->where('email', $email)->delete();

        $token = Str::random(64);

        PendingRegistrationModel::create([
            'email' => $email,
            'display_name' => $displayName,
            'password_hash' => $this->setPassword->hash($password),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ]);

        return $token;
    }

    /**
     * @return int リンクの有効分数
     */
    private function ttlMinutes(): int {
        return Config::integer('chreeid.registration_ttl_minutes');
    }
}
