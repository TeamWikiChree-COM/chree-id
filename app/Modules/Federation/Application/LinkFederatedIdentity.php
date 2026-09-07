<?php
namespace App\Modules\Federation\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Federation\Domain\FederatedIdentity;
use App\Modules\Federation\Domain\FederationLinkConflict;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use RuntimeException;

/**
 * 外部 IdP のアカウントを ChreeID に結びつける。
 *
 * DokuFarm と同じ3段階を踏む。
 *   1. 外部ID で既に紐付いていればそれ
 *   2. 同じメールのアカウントがあれば紐付ける
 *   3. どちらも無ければ新規発行
 */
class LinkFederatedIdentity {
    public function __construct(private readonly ChreeAccountRepository $accounts) {}

    /**
     * @param FederatedIdentity $identity IdP が主張してきた内容
     * @return string 紐付いた ChreeID のアカウントID
     * @throws RuntimeException 紐付けできない場合
     */
    public function execute(FederatedIdentity $identity): string {
        $existing = CredentialModel::query()
            ->where('type', CredentialType::OAUTH)
            ->where('identifier', $identity->credentialIdentifier())
            ->first();

        if ($existing !== null) return $existing->chree_account_id;

        $accountId = $this->findByEmail($identity) ?? $this->createAccount($identity);
        $this->link($accountId, $identity);

        return $accountId;
    }

    /**
     * 同じメールのアカウントを探す。
     *
     * IdP 側でメールが検証されているときだけ紐付ける。
     * 未検証だと、被害者のメールで作った IdP アカウントから乗っ取れてしまう。
     *
     * @param FederatedIdentity $identity
     * @return string|null
     * @throws FederationLinkConflict 未検証のまま既存アカウントとぶつかった場合
     */
    private function findByEmail(FederatedIdentity $identity): ?string {
        if ($identity->email === null) return null;

        $account = $this->accounts->findByEmail($identity->email);
        if ($account === null) return null;

        if (!$identity->emailVerified) {
            throw new FederationLinkConflict('このメールアドレスのアカウントが既にあります');
        }

        return $account->id;
    }

    /**
     * @param FederatedIdentity $identity
     * @return string
     */
    private function createAccount(FederatedIdentity $identity): string {
        return $this->accounts->create(
            AccountOrigin::USER,
            $identity->email,
            $identity->displayName,
        )->id;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param FederatedIdentity $identity
     * @return void
     */
    private function link(string $accountId, FederatedIdentity $identity): void {
        CredentialModel::create([
            'chree_account_id' => $accountId,
            'type' => CredentialType::OAUTH,
            'identifier' => $identity->credentialIdentifier(),
            'data' => [
                'provider' => $identity->provider,
                'email' => $identity->email,
            ],
        ]);
    }
}
