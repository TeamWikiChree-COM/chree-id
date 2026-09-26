<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Infrastructure\CredentialModel;

/**
 * 認証主体が持っている認証手段の一覧。
 */
class AccountCredentials {
    /**
     * @param string $accountId 認証主体のID (ULID)
     * @return list<CredentialModel> 登録順
     */
    public function of(string $accountId): array {
        return array_values(CredentialModel::query()->where('auth_identity_id', $accountId)->orderBy('id')->get()->all());
    }
}
