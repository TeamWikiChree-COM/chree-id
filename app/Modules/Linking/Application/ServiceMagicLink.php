<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\IssueOneTimeToken;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Application\ResolveByEmail;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * サービスが自前のメールリンクを出すための、こちら側の裏付け。
 *
 * **画面もメールもサービスのまま。** ChreeID は「この一度きりの合図は本物か」を
 * 答えるだけで、利用者はブラウザを移動しない (STATUS.md の設計訂正)。
 *
 * 発行した合図は**この口でしか使えない**。ChreeID のセッションは作れないので、
 * サービスに渡しても向こうが ChreeID として振る舞えるようにはならない。
 */
class ServiceMagicLink {
    /** 合図の寿命。メールが届いて開くまでの猶予 */
    private const EXPIRES_MINUTES = 15;

    /** 用途。ChreeID 自身のマジックリンクとは別物として扱う */
    private const PURPOSE = 'service_magic_link';

    public function __construct(
        private readonly ResolveByEmail $byEmail,
        private readonly IssueOneTimeToken $tokens,
    ) {}

    /**
     * 合図を出す。
     *
     * **アドレスを知らなくても、知っているように振る舞わない。**
     * 呼び出し元は結果にかかわらず同じ画面を出すこと。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $email 利用者が入力したアドレス
     * @return string|null 出せなければ null
     */
    public function issue(OAuthClientModel $client, string $email): ?string {
        $serviceAccount = $this->find($client, $email);
        if ($serviceAccount === null) return null;

        // 有効化していない相手には出さない。移行元がメールリンクを
        // 使っていない利用者に、こちら発の経路を勝手に生やさないため
        if (!$this->hasMagicLink($serviceAccount->auth_identity_id)) return null;

        return $this->tokens->execute(
            $serviceAccount->auth_identity_id,
            self::PURPOSE,
            self::EXPIRES_MINUTES,
        );
    }

    /**
     * 合図を使い切る。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $token サービスがメールに載せた平文
     * @return string|null サービス側での利用者の識別子。通らなければ null
     */
    public function consume(OAuthClientModel $client, string $token): ?string {
        $row = OneTimeTokenModel::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('purpose', self::PURPOSE)
            ->first();

        if ($row === null || $row->used_at !== null) return null;
        if ($row->expires_at->isPast()) return null;

        // 出した相手のサービスでしか使えない
        $serviceAccount = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('auth_identity_id', $row->auth_identity_id)
            ->first();

        if ($serviceAccount === null) return null;

        $row->forceFill(['used_at' => now()])->save();

        return $serviceAccount->service_user_id;
    }

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $email 利用者が入力したアドレス
     * @return ServiceAccountModel|null
     */
    private function find(OAuthClientModel $client, string $email): ?ServiceAccountModel {
        foreach ($this->byEmail->candidates($email) as $candidate) {
            $found = ServiceAccountModel::query()
                ->where('client_id', $client->id)
                ->where('auth_identity_id', $candidate->id)
                ->orderBy('id')
                ->first();

            if ($found !== null) return $found;
        }

        return null;
    }

    /**
     * @param string $identityId 認証主体のID (ULID)
     * @return bool
     */
    private function hasMagicLink(string $identityId): bool {
        return CredentialModel::query()
            ->where('auth_identity_id', $identityId)
            ->where('type', CredentialType::MAGIC_LINK)
            ->exists();
    }
}
