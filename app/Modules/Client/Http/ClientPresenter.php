<?php
namespace App\Modules\Client\Http;

use App\Modules\Client\Application\Clients;
use App\Modules\Linking\Application\ServiceAccountCounts;
use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;

/**
 * 管理画面に出す接続サービスの形を作る。
 */
class ClientPresenter {
    private readonly Clients $clients;
    private readonly ServiceAccountCounts $counts;

    public function __construct(Clients $clients, ServiceAccountCounts $counts) {
        $this->clients = $clients;
        $this->counts = $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array {
        $result = [];
        foreach ($this->clients->all() as $client) {
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
            'serviceAccounts' => $this->counts->total($client->id),
            'migratedAccounts' => $this->counts->migrated($client->id),
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
}
