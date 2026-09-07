<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\Totp;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * TOTP を有効にする。
 *
 * 秘密鍵を渡した直後にコードを1回入力させて確認する運用を想定している。
 * 確認前に有効化すると、認証アプリに登録できていない人がログインできなくなる。
 */
class EnableTotp {
    public function __construct(private readonly Totp $totp) {}

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
     * @param string $accountId アカウントID (ULID)
     * @param string $secret generateSecret() で発行した秘密鍵
     * @param string $code 認証アプリが出した数字
     * @return void
     * @throws RuntimeException コードが合わない場合
     */
    public function execute(string $accountId, string $secret, string $code): void {
        if (!$this->totp->verify($secret, $code)) {
            throw new RuntimeException('コードが一致しません');
        }

        CredentialModel::query()->updateOrCreate(
            ['chree_account_id' => $accountId, 'type' => CredentialType::TOTP],
            ['secret' => Crypt::encryptString($secret)],
        );
    }
}
