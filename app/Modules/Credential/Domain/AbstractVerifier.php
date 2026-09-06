<?php
namespace App\Modules\Credential\Domain;

abstract class AbstractVerifier implements CredentialVerifier {
    use BuildsVerificationResult;

    public function isSufficient(): bool {
        return false;
    }
}
