<?php
namespace App\Modules\ExternalLogin\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentityConflict;
use App\Modules\Identity\Application\ResolveByEmail;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use RuntimeException;

/**
 * 外部 IdP のアカウントを ChreeID に結びつける。
 *
 * DokuFarm と同じ3段階を踏む。
 *   1. 外部ID で既に紐付いていればそれ
 *   2. 同じメールのアカウントがあれば紐付ける
 *   3. どちらも無ければ新規発行
 */
class LinkExternalIdentity {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly UserAccounts $userAccounts,
        private readonly ResolveByEmail $byEmail,
    ) {}

    /**
     * @param ExternalIdentity $identity IdP が主張してきた内容
     * @return string 紐付いた ChreeID のアカウントID
     * @throws RuntimeException 紐付けできない場合
     */
    public function execute(ExternalIdentity $identity): string {
        $existing = CredentialModel::query()
            ->where('type', CredentialType::OAUTH)
            ->where('identifier', $identity->credentialIdentifier())
            ->first();

        if ($existing !== null) return $existing->auth_identity_id;

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
     * @param ExternalIdentity $identity
     * @return string|null
     * @throws ExternalIdentityConflict 未検証のまま既存アカウントとぶつかった場合
     */
    private function findByEmail(ExternalIdentity $identity): ?string {
        if ($identity->email === null) return null;

        // 同じアドレスの認証主体は複数ありうる。寄せるなら束ねる人格を持つものへ。
        // 決められないときは寄せずに新規発行する (勝手にどれかへ寄せない)
        $account = $this->byEmail->primary($identity->email);
        if ($account === null) return null;

        if (!$identity->emailVerified) {
            throw new ExternalIdentityConflict('このメールアドレスのアカウントが既にあります');
        }

        return $account->id;
    }

    /**
     * @param ExternalIdentity $identity
     * @return string
     */
    private function createAccount(ExternalIdentity $identity): string {
        $accountId = $this->accounts->create(
            AccountOrigin::USER,
            $identity->email,
            $identity->displayName,
        )->id;

        // IdP が検証済みと言っているなら、こちらでも検証済みとして扱う。
        // ここを立て忘れると ID Token の email_verified が偽のままになり、
        // RP 側でメールを手がかりにした紐付けができなくなる
        if ($identity->emailVerified) $this->accounts->markEmailVerified($accountId);

        // 本人が外部 IdP で作りに来た経路なので、束ねる人格を持たせる
        $this->userAccounts->ensure($accountId);

        return $accountId;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param ExternalIdentity $identity
     * @return void
     */
    private function link(string $accountId, ExternalIdentity $identity): void {
        CredentialModel::create([
            'auth_identity_id' => $accountId,
            'type' => CredentialType::OAUTH,
            'identifier' => $identity->credentialIdentifier(),
            'data' => [
                'provider' => $identity->provider,
                'email' => $identity->email,
            ],
        ]);
    }
}
