<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use Illuminate\Support\Str;

/**
 * 復旧コードを発行する。
 *
 * 平文を返すのはこの1回だけ。DB にはハッシュしか残らない。
 * 発行するたびに古いコードは無効になる。
 */
class GenerateRecoveryCodes {
    /** 一度に発行する本数 */
    private const COUNT = 10;

    /** 1本あたりの文字数。総当たりできない長さにする */
    private const LENGTH = 12;

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<string> 利用者に見せる平文のコード
     */
    public function execute(string $accountId): array {
        // 作り直したら前のコードは使えなくする
        CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::RECOVERY_CODE)
            ->delete();

        $codes = [];
        foreach (range(1, self::COUNT) as $ignored) {
            $code = Str::lower(Str::random(self::LENGTH));
            $codes[] = $code;

            CredentialModel::create([
                'auth_identity_id' => $accountId,
                'type' => CredentialType::RECOVERY_CODE,
                // 十分な長さのランダム値なので、総当たりの心配がなく sha256 で引ける形にする
                'secret' => hash('sha256', $code),
            ]);
        }

        return $codes;
    }

    /**
     * 残っているコードの本数
     *
     * @param string $accountId アカウントID (ULID)
     * @return int
     */
    public function remaining(string $accountId): int {
        return CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::RECOVERY_CODE)
            ->count();
    }
}
