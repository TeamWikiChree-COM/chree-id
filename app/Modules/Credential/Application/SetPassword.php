<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use InvalidArgumentException;

/**
 * パスワードを設定する (新規設定と変更を兼ねる)
 */
class SetPassword {
    /** 短すぎるパスワードを弾く下限 */
    private const MIN_LENGTH = 8;

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $password 平文パスワード
     * @return void
     * @throws InvalidArgumentException 長さが足りない場合
     */
    public function execute(string $accountId, string $password): void {
        $this->executeHashed($accountId, $this->hash($password));
    }

    /**
     * ハッシュ済みのパスワードを設定する。
     *
     * メール確認を挟む登録のように、平文が手元に無い時点で保存する経路のために分けてある。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $hash hash() が返したハッシュ
     * @return void
     */
    public function executeHashed(string $accountId, string $hash): void {
        CredentialModel::query()->updateOrCreate(
            ['auth_identity_id' => $accountId, 'type' => CredentialType::PASSWORD],
            ['secret' => $hash],
        );
    }

    /**
     * 平文パスワードをハッシュにする。
     *
     * @param string $password 平文パスワード
     * @return string
     * @throws InvalidArgumentException 長さが足りない場合
     */
    public function hash(string $password): string {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            throw new InvalidArgumentException(__('credential.password.min_length', ['min' => self::MIN_LENGTH]));
        }

        return password_hash($password, PASSWORD_BCRYPT);
    }
}
