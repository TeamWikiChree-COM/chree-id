<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactor;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Domain\ServiceAuthResult;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Client\Infrastructure\OAuthClientModel;

/**
 * サービス経由のログインの2段階目。VerifyServiceUserPassword が渡した合図と、2要素目のコードを受け取る。
 *
 * **合図は一度きり。** コードを間違えても使い切る。6桁の TOTP は総当たりできる桁数なので、
 * 1回の試行ごとにパスワードの照合 (service-auth で回数を絞っている) を挟ませる。
 */
class VerifyServiceUserSecondFactor {
    public function __construct(
        private readonly VerifyCredential $verify,
        private readonly CompleteAuthentication $complete,
        private readonly AuthIdentityRepository $accounts,
        private readonly ResolveSubject $subjects,
    ) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $challenge パスワードの照合で渡した合図
     * @param string $code 認証アプリのコードか復旧コード
     * @return ServiceAuthResult OK か INVALID
     */
    public function execute(OAuthClientModel $client, string $challenge, string $code): ServiceAuthResult {
        $row = OneTimeTokenModel::query()
            ->where('token_hash', hash('sha256', $challenge))
            ->where('purpose', OneTimeTokenModel::PURPOSE_SERVICE_SECOND_FACTOR)
            ->first();

        if ($row === null || !$row->isUsable()) return ServiceAuthResult::invalid();

        // 出した相手のサービスでしか使えない。他のサービスに合図を横取りされても通らない
        $link = $this->findLink($client, $row->auth_identity_id);
        if ($link === null) return ServiceAuthResult::invalid();

        $row->forceFill(['used_at' => now()])->save();

        // パスワードの照合から間が空くので、その間に停止されたアカウントは通さない
        $account = $this->accounts->findById($row->auth_identity_id);
        if ($account === null || $account->isSuspended()) return ServiceAuthResult::invalid();

        // パスワードは合図を出す前に通っている。判定は AuthenticationPolicy に任せる
        $factors = new VerifiedFactors();
        $factors->add(new VerifiedFactor(CredentialType::PASSWORD, false));

        $type = preg_match('/^\d{6}$/', $code) === 1 ? CredentialType::TOTP : CredentialType::RECOVERY_CODE;
        if (!$this->verify->execute($row->auth_identity_id, $type, ['code' => $code], $factors)->isSuccess()) {
            return ServiceAuthResult::invalid();
        }

        if (!$this->complete->execute($row->auth_identity_id, $factors)) return ServiceAuthResult::invalid();

        return ServiceAuthResult::ok($this->subjects->forServiceAccount($link), $link->service_user_id);
    }

    /**
     * VerifyServiceUserPassword::findLink と同じく、サービスが発行した行を先に取る。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $identityId 認証主体のID (ULID)
     * @return ServiceAccountModel|null
     */
    private function findLink(OAuthClientModel $client, string $identityId): ?ServiceAccountModel {
        return ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('auth_identity_id', $identityId)
            ->orderByRaw('service_user_id IS NULL')
            ->orderBy('id')
            ->first();
    }
}
