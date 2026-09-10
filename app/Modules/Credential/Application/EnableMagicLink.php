<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;

/**
 * メールログインを有効にする。
 *
 * secret を持たない行を作るだけ。これが無いとメールが登録されていても
 * マジックリンクでログインできない (credentials が0件 = ログインする材料が無い、を保つため)。
 */
class EnableMagicLink {
    /**
     * @param string $accountId アカウントID (ULID)
     * @return void
     */
    public function execute(string $accountId): void {
        CredentialModel::query()->firstOrCreate([
            'auth_identity_id' => $accountId,
            'type' => CredentialType::MAGIC_LINK,
        ]);
    }
}
