<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;

/**
 * 外部IdP (Google、GitHub など) の認証手段。
 *
 * 作り方が散らばると、同じ外部アカウントを二重に登録するなどの食い違いが起きるので、ここに集める。
 */
class OAuthCredentials {
    /**
     * @param string $accountId 認証主体のID (ULID)
     * @param string $identifier "google:123456" の形
     * @return bool 既に登録されているか
     */
    public function has(string $accountId, string $identifier): bool {
        return CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::OAUTH)
            ->where('identifier', $identifier)
            ->exists();
    }

    /**
     * @param string $accountId 認証主体のID (ULID)
     * @param string $identifier "google:123456" の形
     * @param array<string, mixed> $data provider など、画面に出すための付帯情報
     * @return void
     */
    public function add(string $accountId, string $identifier, array $data): void {
        CredentialModel::create([
            'auth_identity_id' => $accountId,
            'type' => CredentialType::OAUTH,
            'identifier' => $identifier,
            'data' => $data,
        ]);
    }
}
