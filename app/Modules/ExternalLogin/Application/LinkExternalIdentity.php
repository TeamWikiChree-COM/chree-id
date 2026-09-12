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
     * 同じ外部アカウントに紐付いている認証主体をすべて返す。
     *
     * 分離すると、1つの外部アカウントが複数の認証主体に紐付きうる (KAKUTEI.md)。
     * 複数あるときは、どれとして入るかを本人に選ばせる必要がある。
     *
     * @param ExternalIdentity $identity IdP が主張してきた内容
     * @return list<string> 紐付いている認証主体のID。古い順
     */
    public function candidates(ExternalIdentity $identity): array {
        $found = [];

        $rows = CredentialModel::query()
            ->where('type', CredentialType::OAUTH)
            ->where('identifier', $identity->credentialIdentifier())
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $found[] = $row->auth_identity_id;
        }

        return $found;
    }

    /**
     * @param ExternalIdentity $identity IdP が主張してきた内容
     * @return string 紐付いた ChreeID のアカウントID
     * @throws RuntimeException 紐付けできない場合
     */
    public function execute(ExternalIdentity $identity): string {
        $candidates = $this->candidates($identity);

        // 複数あるときの選択は呼び出し側 (ExternalLoginController) が済ませている
        if ($candidates !== []) return $candidates[0];

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
     * ログイン済みの本人が、後から外部アカウントを足す。
     *
     * ログイン経路の execute() と違い、メールが一致するかは見ない。
     * 本人がログインした状態で始めた連携なので、寄せ先を推測する必要がない。
     *
     * @param string $accountId 連携先のアカウントID (ULID)
     * @param ExternalIdentity $identity IdP が主張してきた内容
     * @return void
     * @throws ExternalIdentityConflict 既に同じアカウントへ連携済みの場合
     */
    public function linkTo(string $accountId, ExternalIdentity $identity): void {
        $exists = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::OAUTH)
            ->where('identifier', $identity->credentialIdentifier())
            ->exists();

        if ($exists) throw new ExternalIdentityConflict('この外部アカウントは既に連携しています');

        $this->link($accountId, $identity);
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
