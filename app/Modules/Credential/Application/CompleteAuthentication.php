<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\AuthenticationPolicy;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\VerifiedFactors;

/**
 * 積んだ要素で認証が成立しているか判定する。
 *
 * 判定そのものは AuthenticationPolicy に固定されていて、ここは材料を集めるだけ。
 */
class CompleteAuthentication {
    public function __construct(
        private readonly CredentialRepository $credentials,
        private readonly AuthenticationPolicy $policy,
    ) {}

    /**
     * @param string $accountId アカウントID (ULID)
     * @param VerifiedFactors $factors 検証に成功した要素
     * @return bool 認証が成立していれば true
     */
    public function execute(string $accountId, VerifiedFactors $factors): bool {
        return $this->policy->isSatisfied(
            $this->credentials->requiresSecondFactor($accountId),
            $factors,
        );
    }
}
