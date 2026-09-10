<?php
namespace App\Modules\Identity\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * 統合のとき、寄せ元から持っていける認証手段を数える。
 *
 * **移せないもの・上書きになるものは出さない** (KAKUTEI.md)。
 * 出したうえで本人に選ばせ、既定は全部オンにする。
 */
class TransferableCredentials {
    /**
     * 寄せ先に既にあると上書きになってしまう型。1つしか持てない。
     *
     * @var list<CredentialType>
     */
    private const SINGULAR = [CredentialType::PASSWORD, CredentialType::TOTP, CredentialType::MAGIC_LINK];

    /**
     * 持っていけない型。
     *
     * パスキーは user handle が認証器に焼き込まれていて、行を移しても
     * `PasskeyVerifier` の照合に落ちる。寄せ先で登録し直してもらう。
     *
     * @var list<CredentialType>
     */
    private const IMMOVABLE = [CredentialType::PASSKEY];

    /**
     * 復旧コードは単独では意味を持たないので、TOTP に追随させる。
     *
     * @var list<CredentialType>
     */
    private const FOLLOWS_TOTP = [CredentialType::RECOVERY_CODE];

    /**
     * 選ばせる候補を返す。
     *
     * @param string $sourceId 寄せ元の認証主体のID (ULID)
     * @param string $targetId 寄せ先の認証主体のID (ULID)
     * @return Collection<int, CredentialModel>
     */
    public function execute(string $sourceId, string $targetId): Collection {
        $taken = $this->typesOf($targetId);

        return $this->credentialsOf($sourceId)->filter(
            fn (CredentialModel $credential): bool => $this->isOffered($credential->type, $taken),
        )->values();
    }

    /**
     * 選ばれたIDのうち、実際に移してよいものだけを残す。
     *
     * **画面から戻ってきたIDは信用しない。** 候補に無いものは落とす。
     *
     * @param string $sourceId 寄せ元の認証主体のID (ULID)
     * @param string $targetId 寄せ先の認証主体のID (ULID)
     * @param list<string> $chosenIds 本人が選んだ認証手段のID
     * @return list<string> 実際に移すID
     */
    public function accept(string $sourceId, string $targetId, array $chosenIds): array {
        $offered = $this->execute($sourceId, $targetId);

        $accepted = $offered
            ->filter(fn (CredentialModel $credential): bool => in_array($credential->id, $chosenIds, true))
            ->pluck('id')
            ->all();

        // TOTP を持っていくなら復旧コードも一緒に。片方だけでは詰む
        if ($this->includesTotp($offered, $accepted)) {
            foreach ($this->credentialsOf($sourceId) as $credential) {
                if (in_array($credential->type, self::FOLLOWS_TOTP, true)) $accepted[] = $credential->id;
            }
        }

        return array_values(array_unique($accepted));
    }

    /**
     * @param CredentialType $type 認証方式
     * @param list<CredentialType> $taken 寄せ先が既に持っている型
     * @return bool 候補として出してよいか
     */
    private function isOffered(CredentialType $type, array $taken): bool {
        if (in_array($type, self::IMMOVABLE, true)) return false;

        // 復旧コードは TOTP と一緒に動くので、単独では選ばせない
        if (in_array($type, self::FOLLOWS_TOTP, true)) return false;

        if (!in_array($type, self::SINGULAR, true)) return true;

        return !in_array($type, $taken, true);
    }

    /**
     * @param Collection<int, CredentialModel> $offered 候補
     * @param list<string> $accepted 選ばれたID
     * @return bool TOTP が選ばれているか
     */
    private function includesTotp(Collection $offered, array $accepted): bool {
        return $offered->contains(
            fn (CredentialModel $credential): bool => $credential->type === CredentialType::TOTP
                && in_array($credential->id, $accepted, true),
        );
    }

    /**
     * @param string $identityId 認証主体のID (ULID)
     * @return Collection<int, CredentialModel>
     */
    private function credentialsOf(string $identityId): Collection {
        return CredentialModel::query()->where('auth_identity_id', $identityId)->orderBy('id')->get();
    }

    /**
     * @param string $identityId 認証主体のID (ULID)
     * @return list<CredentialType>
     */
    private function typesOf(string $identityId): array {
        return $this->credentialsOf($identityId)
            ->map(fn (CredentialModel $credential): CredentialType => $credential->type)
            ->unique()
            ->values()
            ->all();
    }
}
