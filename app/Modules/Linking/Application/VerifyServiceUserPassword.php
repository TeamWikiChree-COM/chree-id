<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Identity\Application\ResolveByEmail;
use App\Modules\Linking\Domain\ServiceAuthResult;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

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
    public function __construct(
        private readonly ResolveByEmail $byEmail,
        private readonly VerifyCredential $verify,
        private readonly CompleteAuthentication $complete,
        private readonly ResolveSubject $subjects,
    ) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $email 利用者が入力したアドレス
     * @param string $password 利用者が入力したパスワード
     * @return ServiceAuthResult
     */
    public function execute(OAuthClientModel $client, string $email, string $password): ServiceAuthResult {
        // 同じアドレスの認証主体は複数ありうる。呼び出し元のサービスに
        // 紐付いているものだけに絞る。これでメールが一意でなくても一意に決まるし、
        // この口が ChreeID 全体のパスワード試行機になることも防げる
        $link = null;
        $account = null;

        foreach ($this->byEmail->candidates($email) as $candidate) {
            $found = ServiceAccountModel::query()
                ->where('client_id', $client->id)
                ->where('auth_identity_id', $candidate->id)
                ->orderBy('id')
                ->first();

            if ($found === null) continue;

            $link = $found;
            $account = $candidate;
            break;
        }

        if ($link === null || $account === null) return ServiceAuthResult::invalid();

        $factors = new VerifiedFactors();
        $verified = $this->verify->execute(
            $account->id,
            CredentialType::PASSWORD,
            ['password' => $password],
            $factors,
        );

        if (!$verified->isSuccess()) return ServiceAuthResult::invalid();

        // 2FA を有効にしているアカウントはパスワードだけでは成立しない。
        // 2要素目をこの口では受け取れないので、ブラウザを介す OIDC へ寄せてもらう
        if (!$this->complete->execute($account->id, $factors)) return ServiceAuthResult::secondFactorRequired();

        return ServiceAuthResult::ok($this->subjects->forServiceAccount($link), $link->service_user_id);
    }
}
