<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Credential\Application\GenerateRecoveryCodes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TOTP を有効にする。
 *
 * 秘密鍵を渡した直後にコードを1回入力させて確認する運用を想定している。
 * 確認前に有効化すると、認証アプリに登録できていない人がログインできなくなる。
 *
 * 復旧コードを同時に発行する。別々に操作できると、控えないまま端末を失った
 * 人が詰む。有効化はここしか通らないので、ここで一緒に出せば「2FA はあるが
 * 復旧手段が無い」という状態を作れなくなる。
 */
class EnableTotp {
    public function __construct(
        private readonly Totp $totp,
        private readonly GenerateRecoveryCodes $recoveryCodes,
    ) {}

    /**
     * 秘密鍵を発行する。まだ有効化はしない。
     *
     * @return string base32 の秘密鍵
     */
    public function generateSecret(): string {
        return $this->totp->generateSecret();
    }

    /**
     * 入力されたコードが合っていれば有効化する。
     *
     * 併せて復旧コードを発行する。**平文を返せるのはこの1回だけ**なので、
     * 呼び出し側は必ず画面へ持っていくこと。
     *
     * 既に有効な人がもう一度通すと復旧コードは作り直しになる。
     * 古いものは使えなくなるが、新しいものをその場で見せるので控え直せる。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $secret generateSecret() で発行した秘密鍵
     * @param string $code 認証アプリが出した数字
     * @return list<string> 利用者に見せる平文の復旧コード
     * @throws RuntimeException コードが合わない場合
     */
    public function execute(string $accountId, string $secret, string $code): array {
        if (!$this->totp->verify($secret, $code)) {
            throw new RuntimeException('コードが一致しません');
        }

        // 片方だけ残ると「復旧手段の無い 2FA」か「使えない復旧コード」になる
        return DB::transaction(function () use ($accountId, $secret): array {
            CredentialModel::query()->updateOrCreate(
                ['auth_identity_id' => $accountId, 'type' => CredentialType::TOTP],
                ['secret' => Crypt::encryptString($secret)],
            );

            return $this->recoveryCodes->execute($accountId);
        });
    }
}
