<?php
namespace App\Modules\Credential\Domain;

class CredentialRegistry {
    /** @var array<string, CredentialVerifier> */
    private array $verifiers = [];

    public function register(CredentialVerifier $verifier): void {
        $this->verifiers[$verifier->type()->value] = $verifier;
    }

    public function get(CredentialType $type): ?CredentialVerifier {
        return $this->verifiers[$type->value] ?? null;
    }

    /** @return array<string, CredentialVerifier> */
    public function all(): array {
        return $this->verifiers;
    }
}
