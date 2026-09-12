<?php
namespace App\Modules\ExternalLogin\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;

/**
 * 本人が連携している外部アカウントの一覧。
 */
class ConnectedExternalAccounts {
    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<array{id: string, provider: string, email: string|null, connectedAt: string|null, lastUsedAt: string|null}>
     */
    public function listFor(string $accountId): array {
        $rows = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::OAUTH)
            ->orderBy('created_at')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $data = is_array($row->data) ? $row->data : [];
            $provider = $data['provider'] ?? null;
            $email = $data['email'] ?? null;

            $result[] = [
                'id' => $row->id,
                // 古い行は data を持たないことがある。identifier の頭がプロバイダ名
                'provider' => is_string($provider) ? $provider : explode(':', (string) $row->identifier)[0],
                'email' => is_string($email) ? $email : null,
                'connectedAt' => $row->created_at?->toDateTimeString(),
                'lastUsedAt' => $row->last_used_at?->toDateTimeString(),
            ];
        }

        return $result;
    }
}
