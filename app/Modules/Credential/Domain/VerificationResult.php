<?php
namespace App\Modules\Credential\Domain;

// 認証結果
readonly class VerificationResult {
    public bool $isSuccess;
    public ?CredentialType $type;

    private function __construct(bool $isSuccess, ?CredentialType $type = null) {
        $this->isSuccess = $isSuccess;
        $this->type = $type;
    }

    public function isSuccess(): bool {
        return $this->isSuccess;
    }

    public function getType(): ?CredentialType {
        return $this->type;
    }

    public static function success(?CredentialType $type): self {
        return new self(true, $type);
    }

    public static function failure(?CredentialType $type = null): self {
        return new self(false, $type);
    }
}
