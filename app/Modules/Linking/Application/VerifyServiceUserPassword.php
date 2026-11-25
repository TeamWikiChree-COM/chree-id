<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\IssueOneTimeToken;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Application\ResolveByEmail;
use App\Modules\Linking\Domain\ServiceAuthResult;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Client\Infrastructure\OAuthClientModel;

/**
 * サービスが自分のログイン画面で受け取ったメール+パスワードを、こちらに問い合わせる。
 *
 * サービス側のアカウントは見せかけで、実体は ChreeID (初期はサービスアカウント) なので、
 * 照合できる場所はここしかない。サービスにパスワードを持たせないための口。
 *
 * ブラウザのリダイレクトを挟まないぶん、利用者から見た画面は今までと変わらない。
 * 認証成立の判定は AuthenticationPolicy を通る (CompleteAuthentication 経由)。
 */
class VerifyServiceUserPassword {
    /** 2要素目を待つ時間。認証アプリを開いてコードを打つまでの猶予 */
    public const CHALLENGE_MINUTES = 5;

    public function __construct(
        private readonly ResolveByEmail $byEmail,
        private readonly VerifyCredential $verify,
        private readonly CompleteAuthentication $complete,
        private readonly ResolveSubject $subjects,
        private readonly IssueOneTimeToken $tokens,
    ) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $email 利用者が入力したアドレス
     * @param string $password 利用者が入力したパスワード
     * @return ServiceAuthResult
     */
    public function execute(OAuthClientModel $client, string $email, string $password): ServiceAuthResult {
        $link = $this->findLink($client, $email);
        if ($link === null) return ServiceAuthResult::invalid();

        $accountId = $link->auth_identity_id;
        $factors = new VerifiedFactors();
        $verified = $this->verify->execute(
            $accountId,
            CredentialType::PASSWORD,
            ['password' => $password],
            $factors,
        );

        if (!$verified->isSuccess()) return ServiceAuthResult::invalid();

        // 2FA を有効にしているアカウントはパスワードだけでは成立しない。
        // パスワードが通った証として合図を渡し、2要素目と一緒に送り返してもらう (VerifyServiceUserSecondFactor)。
        // パスワードをもう一度流させずに済むので、サービスが平文を持ち回らなくてよい
        if (!$this->complete->execute($accountId, $factors)) {
            return ServiceAuthResult::secondFactorRequired(
                $this->tokens->execute($accountId, OneTimeTokenModel::PURPOSE_SERVICE_SECOND_FACTOR, self::CHALLENGE_MINUTES),
            );
        }

        return ServiceAuthResult::ok($this->subjects->forServiceAccount($link), $link->service_user_id);
    }

    /**
     * 同じアドレスの認証主体は複数ありうる。呼び出し元のサービスに
     * 紐付いているものだけに絞る。これでメールが一意でなくても一意に決まるし、
     * この口が ChreeID 全体のパスワード試行機になることも防げる。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $email 利用者が入力したアドレス
     * @return ServiceAccountModel|null 紐付きが無ければ null
     */
    private function findLink(OAuthClientModel $client, string $email): ?ServiceAccountModel {
        foreach ($this->byEmail->candidates($email) as $candidate) {
            // サービスが発行した行 (service_user_id がある) を先に取る。移行で OIDC のログインだけの行が
            // 古い ID で並んでいると、識別子が空のまま返り、サービスが本人のログインとみなせなくなる
            $found = ServiceAccountModel::query()
                ->where('client_id', $client->id)
                ->where('auth_identity_id', $candidate->id)
                ->orderByRaw('service_user_id IS NULL')
                ->orderBy('id')
                ->first();

            if ($found !== null) return $found;
        }

        return null;
    }
}
