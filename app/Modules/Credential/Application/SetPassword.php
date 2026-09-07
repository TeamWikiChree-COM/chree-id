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
        if (mb_strlen($password) < self::MIN_LENGTH) {
            throw new InvalidArgumentException('パスワードは' . self::MIN_LENGTH . '文字以上にしてください');
        }

        CredentialModel::query()->updateOrCreate(
            ['chree_account_id' => $accountId, 'type' => CredentialType::PASSWORD],
            ['secret' => password_hash($password, PASSWORD_BCRYPT)],
        );
    }
}
