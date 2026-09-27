<?php
namespace App\Modules\Provider\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Domain\Claims\ScopeRegistry;

/**
 * 引換券の中身を、サービスに渡す sub と属性にする。
 *
 * 渡す直前にもう一度、停止を確かめる。サインインが済んでから引換券が使われるまでの間に
 * 止められていても、渡してしまわないため。
 */
class ResolveSignInGrant {
    private readonly AuthIdentityRepository $accounts;
    private readonly ResolveSubject $subjects;
    private readonly ScopeRegistry $scopes;

    public function __construct(AuthIdentityRepository $accounts, ResolveSubject $subjects, ScopeRegistry $scopes) {
        $this->accounts = $accounts;
        $this->subjects = $subjects;
        $this->scopes = $scopes;
    }

    /**
     * @param SignInGrant $grant
     * @return SignedInService|null もう渡せなければ null
     */
    public function execute(SignInGrant $grant): ?SignedInService {
        $client = OAuthClientModel::query()->find($grant->clientId);
        if ($client === null || !$client->trust->isUsable()) return null;

        $serviceAccount = ServiceAccountModel::query()->find($grant->serviceAccountId);
        if ($serviceAccount === null || $serviceAccount->client_id !== $client->id) return null;

        $account = $this->accounts->findById($serviceAccount->auth_identity_id);
        if ($account === null || $account->isSuspended()) return null;

        return new SignedInService(
            $client->id,
            $this->subjects->forServiceAccount($serviceAccount),
            $this->scopes->claimsFor($account, $grant->scopes, $serviceAccount->id),
        );
    }
}
