<?php
namespace App\Modules\Linking\Http;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Linking\Application\SplitServiceAccount;

/**
 * 分離で持っていける認証手段を、画面に出す形にする。分離と退会の画面で使う。
 */
class SplitCredentialOptions {
    private readonly SplitServiceAccount $split;

    public function __construct(SplitServiceAccount $split) {
        $this->split = $split;
    }

    /**
     * @param string $identityId 寄せ元の認証主体のID (ULID)
     * @return list<array{id: string, type: string, provider: string|null, detail: string|null}>
     */
    public function of(string $identityId): array {
        $options = [];
        foreach ($this->split->options($identityId) as $credential) {
            $data = is_array($credential->data) ? $credential->data : [];
            // 同じ種別が並んだとき (Google を2つ連携しているなど) に見分けが付くように
            $detail = $data['email'] ?? null;
            // 外部ログインはどこの連携かを識別子の頭 (google:… など) で持っている
            $provider = $credential->type === CredentialType::OAUTH ? explode(':', (string) $credential->identifier)[0] : null;
            $options[] = [
                'id' => $credential->id,
                'type' => $credential->type->value,
                'provider' => $provider,
                'detail' => is_string($detail) ? $detail : null,
            ];
        }

        return $options;
    }
}
