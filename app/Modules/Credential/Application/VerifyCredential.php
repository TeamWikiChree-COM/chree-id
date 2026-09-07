<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialRegistry;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerificationResult;
use App\Modules\Credential\Domain\VerifiedFactor;
use App\Modules\Credential\Domain\VerifiedFactors;

/**
 * 認証方式をひとつ検証し、成功したら要素として積む。
 *
 * 認証が成立したかの判定はここではなく AuthenticationPolicy が行う。
 */
class VerifyCredential {
    public function __construct(private readonly CredentialRegistry $registry) {}

    /**
     * @param string $accountId アカウントID (ULID)
     * @param CredentialType $type 検証する認証方式
     * @param array<string, mixed> $input 方式ごとの入力値
     * @param VerifiedFactors $factors 成功した要素の積み先
     * @return VerificationResult
     */
    public function execute(string $accountId, CredentialType $type, array $input, VerifiedFactors $factors): VerificationResult {
        $verifier = $this->registry->get($type);
        if ($verifier === null) return VerificationResult::failure($type);

        $result = $verifier->verify($accountId, $input);
        if (!$result->isSuccess()) return $result;

        $factors->add(new VerifiedFactor($type, $verifier->isSufficient()));

        return $result;
    }
}
