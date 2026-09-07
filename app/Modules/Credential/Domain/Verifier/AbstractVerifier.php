<?php
namespace App\Modules\Credential\Domain\Verifier;

use App\Modules\Credential\Domain\BuildsVerificationResult;

abstract class AbstractVerifier implements CredentialVerifier {
    use BuildsVerificationResult;

    public function isSufficient(): bool {
        return false;
    }
}
