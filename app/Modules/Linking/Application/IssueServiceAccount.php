<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\AdoptPasswordHash;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountLinkModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Facades\DB;

/**
 * サービス側の利用者に ChreeID を1つ用意する。
 *
 * 利用者に登録を強いず、サービスを使った時点で裏で発行するための入口。
 * 本人は何も気付かないまま ChreeID を持つことになるので、
 * 発行できるのは公式サービスだけに絞っている (呼び出し側で確認)。
 *
 * 返す sub は ResolveSubject が決める。OIDC でログインしたときと
 * 同じ値でなければ、サービス側から見て別人になってしまう。
 */
class IssueServiceAccount {
    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly ResolveSubject $subjects,
        private readonly AdoptPasswordHash $passwords,
    ) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @param string|null $email サービスが把握しているアドレス
     * @param bool $emailVerified サービス側で到達性を確認済みか
     * @param string|null $displayName 表示名
     * @param string|null $passwordHash 移行元が持っていた bcrypt ハッシュ
     * @return string サービスに渡す sub
     */
    public function execute(
        OAuthClientModel $client,
        string $serviceUserId,
        ?string $email = null,
        bool $emailVerified = false,
        ?string $displayName = null,
        ?string $passwordHash = null,
    ): string {
        $existing = ServiceAccountLinkModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        // 二度目以降は同じものを返す。呼び出し側が何度叩いても増えない
        if ($existing !== null) return $this->subjects->execute($client, $existing->chree_account_id);

        $accountId = DB::transaction(
            fn (): string => $this->link($client, $serviceUserId, $email, $emailVerified, $displayName),
        );

        // 移行元のパスワードをそのまま使えるようにする。
        // これが無いと、認証手段の無いアカウントが出来上がって本人が入れない
        if ($passwordHash !== null) $this->passwords->execute($accountId, $passwordHash);

        return $this->subjects->execute($client, $accountId);
    }

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @param string|null $email サービスが把握しているアドレス
     * @param bool $emailVerified サービス側で到達性を確認済みか
     * @param string|null $displayName 表示名
     * @return string アカウントID (ULID)
     */
    private function link(
        OAuthClientModel $client,
        string $serviceUserId,
        ?string $email,
        bool $emailVerified,
        ?string $displayName,
    ): string {
        $accountId = $this->resolveAccount($email, $emailVerified, $displayName);

        ServiceAccountLinkModel::create([
            'client_id' => $client->id,
            'chree_account_id' => $accountId,
            'service_user_id' => $serviceUserId,
            'service_email' => $email,
        ]);

        return $accountId;
    }

    /**
     * 既にある ChreeID に相乗りするか、新しく作るかを決める。
     *
     * 同じ人が既に ChreeID を持っているなら、そこへ寄せたい。
     * ただし寄せる判断はアドレスの一致だけを根拠にするので、
     * サービス側とこちら側の両方で到達性が確かめられている場合に限る。
     * 片方でも未確認だと、他人のアドレスを名乗るだけでアカウントを奪える。
     *
     * @param string|null $email サービスが把握しているアドレス
     * @param bool $emailVerified サービス側で到達性を確認済みか
     * @param string|null $displayName 表示名
     * @return string アカウントID (ULID)
     */
    private function resolveAccount(?string $email, bool $emailVerified, ?string $displayName): string {
        $existing = $email === null ? null : $this->accounts->findByEmail($email);

        if ($existing !== null) {
            if ($emailVerified && $existing->isEmailVerified()) return $existing->id;

            // 既に使われているアドレスは持たせられない。素の器だけ作る
            return $this->accounts->create(AccountOrigin::SERVICE, null, $displayName)->id;
        }

        $account = $this->accounts->create(AccountOrigin::SERVICE, $email, $displayName);

        // 公式サービスが確認済みと言うなら、こちらでも確認済みとして扱う。
        // 外部 IdP の email_verified を信じているのと同じ判断
        if ($email !== null && $emailVerified) $this->accounts->markEmailVerified($account->id);

        return $account->id;
    }
}
