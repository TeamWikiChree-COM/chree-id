<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\AccountCredentials;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use Illuminate\Support\Facades\DB;

/**
 * サービスアカウントを、新しい AuthIdentity へ切り離す。統合の逆操作。
 *
 * 「統合をやめたいが、そのサービスは使い続けたい」に答えるためのもの。
 * これがあるので統合は不可逆にならない (KAKUTEI.md)。
 *
 * **`sub` は ServiceAccount の持ち物なので、分離してもサービスから見た識別子は変わらない。**
 * 向こうは何も気付かない。
 *
 * 認証手段は一から作らせず、寄せ元から**複製する**。何を持っていくかは本人が選ぶ。
 */
class SplitServiceAccount {
    /**
     * 持っていけない型。
     *
     * パスキーは user handle が認証器に焼き込まれているので、複製しても
     * `PasskeyVerifier` の照合に落ちる。新しい側で登録し直してもらう。
     *
     * @var list<CredentialType>
     */
    private const IMMOVABLE = [CredentialType::PASSKEY];

    /**
     * 選ばせずに TOTP へ追随させる型。
     *
     * 復旧コードは1本1行なので、選択肢に並べると同じものが10個並ぶ。単独では意味も持たない。
     *
     * @var list<CredentialType>
     */
    private const FOLLOWS_TOTP = [CredentialType::RECOVERY_CODE];

    private readonly AuthIdentityRepository $accounts;

    private readonly AccountCredentials $credentials;

    public function __construct(AuthIdentityRepository $accounts, AccountCredentials $credentials) {
        $this->accounts = $accounts;
        $this->credentials = $credentials;
    }

    /**
     * 分離の対象にするサービスアカウント。本人のものでなければ引けない。
     *
     * @param string $serviceAccountId サービスアカウントのID (ULID)
     * @param string $identityId 認証主体のID (ULID)
     * @return ServiceAccountModel|null 本人のものでなければ null
     */
    public function target(string $serviceAccountId, string $identityId): ?ServiceAccountModel {
        return ServiceAccountModel::query()
            ->whereKey($serviceAccountId)
            ->where('auth_identity_id', $identityId)
            ->first();
    }

    /**
     * 分離できる認証手段を返す。
     *
     * @param string $identityId 寄せ元の認証主体のID (ULID)
     * @return list<CredentialModel>
     */
    public function options(string $identityId): array {
        $options = [];

        foreach ($this->credentials->of($identityId) as $credential) {
            if (in_array($credential->type, self::IMMOVABLE, true)) continue;
            if (in_array($credential->type, self::FOLLOWS_TOTP, true)) continue;

            $options[] = $credential;
        }

        return $options;
    }

    /**
     * @param ServiceAccountModel $serviceAccount 切り離すサービスアカウント
     * @param string|null $email 新しい側の連絡先。省略すると寄せ元のものを引き継ぐ
     * @param string|null $displayName 新しい側の表示名
     * @param list<string> $credentialIds 複製する認証手段のID
     * @return string 新しい認証主体のID (ULID)
     * @throws SplitException
     */
    public function execute(
        ServiceAccountModel $serviceAccount,
        ?string $email,
        ?string $displayName,
        array $credentialIds,
    ): string {
        $sourceId = $serviceAccount->auth_identity_id;
        $source = $this->accounts->findById($sourceId);
        if ($source === null) throw new SplitException(SplitException::NOT_FOUND);

        $carried = $this->carried($sourceId, $credentialIds);

        // 認証手段が1つも無いと、分離した先に誰も入れなくなる
        if ($carried === []) throw new SplitException(SplitException::NO_CREDENTIAL);

        // メールは認証主体を一意に決めないので、同じものを引き継いでよい。
        // どちらを指すかはサービス経由なら絞れる (ResolveByEmail)
        $email ??= $source->email;
        $displayName ??= $source->displayName;

        return DB::transaction(function () use ($serviceAccount, $email, $displayName, $carried): string {
            // 由来としては「サービスのために起きた」ので service。
            // 束ねる人格 (UserAccount) は持たせない。持つのは本人が改めて決めること
            $created = $this->accounts->create(AccountOrigin::SERVICE, $email, $displayName);

            foreach ($carried as $credential) {
                $this->copyTo($created->id, $credential);
            }

            $serviceAccount->forceFill([
                'auth_identity_id' => $created->id,
                // 束ね直したわけではないので、引き取り済みの記録は持ち越さない
                'claimed_at' => null,
                // 割り当てていたのは元のアカウントのアドレス。分けた先はそれを持っていない
                'email' => null,
            ])->save();

            return $created->id;
        });
    }

    /**
     * 選ばれたIDのうち、実際に持っていけるものだけを残す。
     *
     * **画面から戻ってきたIDは信用しない。**
     *
     * @param string $identityId 寄せ元の認証主体のID (ULID)
     * @param list<string> $credentialIds 本人が選んだID
     * @return list<CredentialModel>
     */
    private function carried(string $identityId, array $credentialIds): array {
        $carried = [];
        $takesTotp = false;

        foreach ($this->options($identityId) as $credential) {
            if (!in_array($credential->id, $credentialIds, true)) continue;

            $carried[] = $credential;
            if ($credential->type === CredentialType::TOTP) $takesTotp = true;
        }

        // TOTP を持っていくなら復旧コードも一緒に。片方だけでは詰む
        if (!$takesTotp) return $carried;

        $codes = CredentialModel::query()
            ->where('auth_identity_id', $identityId)
            ->whereIn('type', array_map(static fn (CredentialType $type): string => $type->value, self::FOLLOWS_TOTP))
            ->get()
            ->all();

        return array_merge($carried, $codes);
    }

    /**
     * 複製する。**寄せ元からは消さない。**
     *
     * @param string $identityId 新しい認証主体のID (ULID)
     * @param CredentialModel $credential 複製元
     * @return void
     */
    private function copyTo(string $identityId, CredentialModel $credential): void {
        CredentialModel::create([
            'auth_identity_id' => $identityId,
            'type' => $credential->type,
            'identifier' => $credential->identifier,
            'secret' => $credential->secret,
            'data' => $credential->data,
        ]);
    }
}
