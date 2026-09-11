<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\AdoptPasswordHash;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Facades\DB;

/**
 * サービス側の利用者に ChreeID を1つ用意する。
 *
 * 利用者に登録を強いず、サービスを使った時点で裏で発行するための入口。
 * 本人は何も気付かないまま ChreeID を持つことになるので、
 * 発行できるのは許可したクライアントだけに絞っている (呼び出し側で確認)。
 *
 * 返す sub は ResolveSubject が決める。OIDC でログインしたときと
 * 同じ値でなければ、サービス側から見て別人になってしまう。
 *
 * ここで既存の ChreeID に寄せることはしない。アドレスが一致していても、
 * 統合するかどうかは本人が決める (ARCHITECTURE.md 8.7)。
 */
class IssueServiceAccount {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
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
     * @param string|null $knownSub 既に分かっている sub。紐付けだけ作り直したいときに渡す
     * @param list<string> $externalIdentities 移行元が把握している外部IdPの識別子 ("google:123" 形式)
     * @return string サービスに渡す sub
     */
    public function execute(
        OAuthClientModel $client,
        string $serviceUserId,
        ?string $email = null,
        bool $emailVerified = false,
        ?string $displayName = null,
        ?string $passwordHash = null,
        ?string $knownSub = null,
        array $externalIdentities = [],
    ): string {
        $existing = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        // 二度目以降は同じものを返す。呼び出し側が何度叩いても増えない
        if ($existing !== null) return $this->subjects->forServiceAccount($existing);

        // OIDC でログインしただけで、サービス側の識別子をまだ知らない行がありうる。
        // そこへ結び付け直す。新しくアカウントを作ると本人が2つに割れてしまう
        if ($knownSub !== null) {
            $bound = $this->bind($client, $serviceUserId, $knownSub);
            if ($bound !== null) return $bound;
        }

        $accountId = DB::transaction(
            fn (): string => $this->link($client, $serviceUserId, $email, $emailVerified, $displayName),
        );

        // 移行元のパスワードをそのまま使えるようにする。
        // これが無いと、認証手段の無いアカウントが出来上がって本人が入れない
        if ($passwordHash !== null) $this->passwords->execute($accountId, $passwordHash);

        // Google だけで使っていた人は、パスワードを持っていない。
        // 外部IdPも引き継がないと、移行の画面に選べるものが1つも出なくなる
        foreach ($externalIdentities as $identity) {
            $this->adoptExternal($accountId, $identity);
        }

        return $this->subjects->execute($client, $accountId);
    }

    /**
     * 移行元が把握している外部IdPの連携を引き継ぐ。
     *
     * 同じ外部アカウントが複数の認証主体に紐付くのは許す (分離で起きうる)。
     * ログイン時にどちらとして入るかは選ばせる。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $identity "google:123456" の形
     * @return void
     */
    private function adoptExternal(string $accountId, string $identity): void {
        // provider と subject が揃っていないものは受け取らない
        if (!str_contains($identity, ':')) return;

        [$provider] = explode(':', $identity, 2);
        if ($provider === '') return;

        $already = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('type', CredentialType::OAUTH)
            ->where('identifier', $identity)
            ->exists();

        if ($already) return;

        CredentialModel::create([
            'auth_identity_id' => $accountId,
            'type' => CredentialType::OAUTH,
            'identifier' => $identity,
            'data' => ['provider' => $provider],
        ]);
    }

    /**
     * 既にある行に、サービス側の識別子を書き入れる。
     *
     * **そのサービス自身の行にしか書けない。** 他所のアカウントを名乗ることはできない。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @param string $knownSub サービスが既に知っている sub
     * @return string|null 結び付けられなければ null
     */
    private function bind(OAuthClientModel $client, string $serviceUserId, string $knownSub): ?string {
        $found = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('sub', $knownSub)
            ->first();

        // 既に別の利用者に割り当たっている行は触らない
        if ($found === null || $found->service_user_id !== null) return null;

        $found->forceFill(['service_user_id' => $serviceUserId])->save();

        return $this->subjects->forServiceAccount($found);
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

        ServiceAccountModel::create([
            'client_id' => $client->id,
            'auth_identity_id' => $accountId,
            'service_user_id' => $serviceUserId,
            'service_email' => $email,
        ]);

        return $accountId;
    }

    /**
     * サービスアカウントの器を作る。
     *
     * **アドレスが一致しても既存のアカウントには寄せない。** 寄せるかどうかは
     * 本人が決めることで、こちらが裏で決めてよいことではない。同じアドレスを
     * 使っているだけの別アカウントということも普通にある。
     * 一致は「統合候補」として本人に見せる材料に留め、統合は本人の操作で行う。
     *
     * @param string|null $email サービスが把握しているアドレス
     * @param bool $emailVerified サービス側で到達性を確認済みか
     * @param string|null $displayName 表示名
     * @return string アカウントID (ULID)
     */
    private function resolveAccount(?string $email, bool $emailVerified, ?string $displayName): string {
        // 同じアドレスのサービスアカウントが複数あるのは正常な状態 (ARCHITECTURE.md 8.3)。
        // 以前は email が一意だったので2人目以降を null にしていたが、その回避はもう要らない
        $account = $this->accounts->create(AccountOrigin::SERVICE, $email, $displayName);

        // 発行を許したサービスが確認済みと言うなら、こちらでも確認済みとして扱う。
        // 外部 IdP の email_verified を信じているのと同じ判断
        if ($email !== null && $emailVerified) $this->accounts->markEmailVerified($account->id);

        return $account->id;
    }
}
