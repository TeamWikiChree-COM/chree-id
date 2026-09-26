<?php
namespace App\Modules\Provider\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Domain\Claims\ScopeRegistry;
use App\Modules\Provider\Infrastructure\AccessTokenModel;

/**
 * アクセストークンから userinfo のクレームを組み立てる。
 *
 * トークンの scope の範囲でだけ返す。
 */
class ResolveUserinfo {
    private readonly AuthIdentityRepository $accounts;
    private readonly ResolveSubject $subjects;
    private readonly ScopeRegistry $scopes;

    public function __construct(AuthIdentityRepository $accounts, ResolveSubject $subjects, ScopeRegistry $scopes) {
        $this->accounts = $accounts;
        $this->subjects = $subjects;
        $this->scopes = $scopes;
    }

    /**
     * @param string $bearer 平文のアクセストークン
     * @return array<string, mixed>|null トークンが使えなければ null
     */
    public function execute(string $bearer): ?array {
        $token = AccessTokenModel::query()->where('token_hash', hash('sha256', $bearer))->first();
        if ($token === null || !$token->isUsable()) return null;

        $account = $this->accounts->findById($token->auth_identity_id);
        $client = OAuthClientModel::query()->find($token->client_id);
        if ($account === null || $client === null) return null;

        // sub は発行時と同じ値でなければ RP 側で突き合わせできない。
        // トークンに控えたサービスアカウントを使う
        $serviceAccount = $token->service_account_id === null
            ? null
            : ServiceAccountModel::query()->find($token->service_account_id);

        $subject = $serviceAccount === null
            ? $this->subjects->execute($client, $account->id)
            : $this->subjects->forServiceAccount($serviceAccount);

        return array_merge(
            ['sub' => $subject],
            $this->scopes->claimsFor($account, $token->scopes(), $serviceAccount?->id),
        );
    }
}
