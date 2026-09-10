<?php
namespace App\Modules\Linking\Application;

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

    public function __construct(private readonly AuthIdentityRepository $accounts) {}

    /**
     * 分離できる認証手段を返す。
     *
     * @param string $identityId 寄せ元の認証主体のID (ULID)
     * @return list<CredentialModel>
     */
    public function options(string $identityId): array {
        $options = [];

        foreach (CredentialModel::query()->where('auth_identity_id', $identityId)->orderBy('id')->get() as $credential) {
            if (in_array($credential->type, self::IMMOVABLE, true)) continue;

            $options[] = $credential;
        }

        return $options;
    }

    /**
     * @param ServiceAccountModel $serviceAccount 切り離すサービスアカウント
     * @param string $email 新しい側の連絡先。寄せ元とは別のアドレスが要る
     * @param string|null $displayName 新しい側の表示名
     * @param list<string> $credentialIds 複製する認証手段のID
     * @return string 新しい認証主体のID (ULID)
     * @throws SplitException
     */
    public function execute(
        ServiceAccountModel $serviceAccount,
        string $email,
        ?string $displayName,
        array $credentialIds,
    ): string {
        $sourceId = $serviceAccount->auth_identity_id;

        $carried = $this->carried($sourceId, $credentialIds);

        // 認証手段が1つも無いと、分離した先に誰も入れなくなる
        if ($carried === []) throw new SplitException(SplitException::NO_CREDENTIAL);

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

        foreach ($this->options($identityId) as $credential) {
            if (in_array($credential->id, $credentialIds, true)) $carried[] = $credential;
        }

        return $carried;
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
