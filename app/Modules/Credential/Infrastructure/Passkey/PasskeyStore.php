<?php
namespace App\Modules\Credential\Infrastructure\Passkey;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use Webauthn\PublicKeyCredentialSource;

/**
 * パスキーの保存と取り出し。
 *
 * credentials の1行が1つのパスキーに対応する。
 *   identifier … credential_id (base64url)
 *   secret     … PublicKeyCredentialSource の JSON。公開鍵なので秘密ではない
 *   data       … 端末名など
 */
class PasskeyStore {
    public function __construct(private readonly PasskeySerializer $serializer) {}

    /**
     * @param string $accountId アカウントID (ULID)
     * @param PublicKeyCredentialSource $source
     * @param string|null $label 利用者が付ける端末名
     * @return void
     */
    public function save(string $accountId, PublicKeyCredentialSource $source, ?string $label = null): void {
        CredentialModel::query()->updateOrCreate(
            [
                'type' => CredentialType::PASSKEY,
                'identifier' => $this->encodeId($source->publicKeyCredentialId),
            ],
            [
                'auth_identity_id' => $accountId,
                'secret' => $this->serializer->encodeSource($source),
                'data' => ['label' => $label],
            ],
        );
    }

    /**
     * @param string $credentialId base64url の credential_id
     * @return PublicKeyCredentialSource|null
     */
    public function find(string $credentialId): ?PublicKeyCredentialSource {
        $row = CredentialModel::query()
            ->where('type', CredentialType::PASSKEY)
            ->where('identifier', $credentialId)
            ->first();

        if ($row === null || $row->secret === null) return null;

        return $this->serializer->decodeSource($row->secret);
    }

    /**
     * 署名カウンタは増える一方なので、認証のたびに更新する。
     * 巻き戻ったら複製された端末の可能性がある。
     *
     * @param PublicKeyCredentialSource $source
     * @return void
     */
    public function updateCounter(PublicKeyCredentialSource $source): void {
        CredentialModel::query()
            ->where('type', CredentialType::PASSKEY)
            ->where('identifier', $this->encodeId($source->publicKeyCredentialId))
            ->update([
                'secret' => $this->serializer->encodeSource($source),
                'last_used_at' => now(),
            ]);
    }

    /**
     * そのアカウントが登録済みの credential_id 一覧
     *
     * @param string $accountId アカウントID (ULID)
     * @return list<string> base64url の credential_id
     */
    public function credentialIdsOf(string $accountId): array {
        $ids = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::PASSKEY)
            ->pluck('identifier')
            ->all();

        $result = [];
        foreach ($ids as $id) {
            if (is_string($id)) $result[] = $id;
        }

        return $result;
    }

    /**
     * @param string $raw 生の credential_id
     * @return string base64url
     */
    public function encodeId(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
