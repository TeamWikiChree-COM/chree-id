<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;

/**
 * 画面に並べる認証手段の一覧。
 *
 * ダッシュボードとセキュリティ設定で同じものを出す。**種別名だけでは足りない。**
 * 外部アカウントやパスキーは同じ種別を複数持てるので、どれがどれか見分けられる
 * 手がかり (連携先のアドレス、端末名) を必ず添える。
 */
class ListCredentials {
    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<array{id: string, type: string, label: string|null, provider: string|null, detail: string|null, lastUsedAt: string|null, createdAt: string|null}>
     */
    public function execute(string $accountId): array {
        $rows = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            // 復旧コードは1本1行なので、一覧にそのまま並べても意味がない
            ->where('type', '!=', CredentialType::RECOVERY_CODE->value)
            ->orderBy('type')
            ->orderBy('created_at')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $data = is_array($row->data) ? $row->data : [];

            $result[] = [
                'id' => $row->id,
                'type' => $row->type->value,
                'label' => $this->stringOrNull($data['label'] ?? null),
                'provider' => $this->providerOf($row, $data),
                'detail' => $this->stringOrNull($data['email'] ?? null),
                'lastUsedAt' => $row->last_used_at?->toDateTimeString(),
                'createdAt' => $row->created_at?->toDateTimeString(),
            ];
        }

        return $result;
    }

    /**
     * 外部アカウントの連携先。
     *
     * data を持たない古い行のために identifier からも拾う ("google:123456" の頭)。
     *
     * @param CredentialModel $row
     * @param array<string, mixed> $data 型ごとの付随情報
     * @return string|null 外部アカウント以外は null
     */
    private function providerOf(CredentialModel $row, array $data): ?string {
        if ($row->type !== CredentialType::OAUTH) return null;

        $provider = $this->stringOrNull($data['provider'] ?? null);
        if ($provider !== null) return $provider;

        return explode(':', (string) $row->identifier)[0];
    }

    /**
     * @param mixed $value 期待は文字列だが、古い行では欠けていることがある
     * @return string|null
     */
    private function stringOrNull(mixed $value): ?string {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
