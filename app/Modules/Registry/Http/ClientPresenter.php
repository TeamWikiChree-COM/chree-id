<?php
namespace App\Modules\Registry\Http;

use App\Modules\Identity\Infrastructure\UserAccountModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 管理画面に出す接続サービスの形を作る。
 */
class ClientPresenter {
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array {
        $result = [];
        foreach (OAuthClientModel::query()->orderBy('name')->get() as $client) {
            $result[] = $this->toArray($client);
        }

        return $result;
    }

    /**
     * @param OAuthClientModel $client
     * @return array{id: string, name: string, names: array<string, string>, redirectUris: list<string>, scopes: string, isConfidential: bool, trust: string, skipsConsent: bool, canProvision: bool, iconUrl: string|null, settingsUrl: string|null, reviewRequestedAt: string|null, hasOwner: bool, serviceAccounts: int, migratedAccounts: int, createdAt: string|null}
     */
    public function toArray(OAuthClientModel $client): array {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'names' => $client->names ?? [],
            'redirectUris' => $client->redirect_uris,
            'scopes' => $client->scopes,
            'isConfidential' => $client->is_confidential,
            'trust' => $client->trust->value,
            'skipsConsent' => $client->skips_consent,
            'canProvision' => $client->can_provision,
            'iconUrl' => $client->icon_url,
            'settingsUrl' => $client->settings_url,
            'reviewRequestedAt' => $client->review_requested_at?->format('Y/m/d H:i'),
            'hasOwner' => $client->owner_id !== null,
            // 移行元へ落とす経路をいつ消せるかの目安になる
            'serviceAccounts' => $this->countServiceAccounts($client->id),
            'migratedAccounts' => $this->countMigrated($client->id),
            'createdAt' => $client->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function trustOptions(): array {
        return [
            ['value' => ServiceTrust::OFFICIAL->value, 'label' => __('admin.client.trust.official')],
            ['value' => ServiceTrust::APPROVED->value, 'label' => __('admin.client.trust.approved')],
            ['value' => ServiceTrust::UNAPPROVED->value, 'label' => __('admin.client.trust.unapproved')],
            ['value' => ServiceTrust::DISABLED->value, 'label' => __('admin.client.trust.disabled')],
        ];
    }

    /**
     * @param string $clientId サービスの client_id
     * @return int このサービスのサービスアカウント数
     */
    private function countServiceAccounts(string $clientId): int {
        return ServiceAccountModel::query()->where('client_id', $clientId)->count();
    }

    /**
     * 束ねる人格を持つに至った数。移行がどこまで進んでいるかの目安。
     *
     * @param string $clientId サービスの client_id
     * @return int
     */
    private function countMigrated(string $clientId): int {
        return ServiceAccountModel::query()
            ->where('client_id', $clientId)
            ->whereIn('auth_identity_id', UserAccountModel::query()->select('auth_identity_id'))
            ->count();
    }
}
