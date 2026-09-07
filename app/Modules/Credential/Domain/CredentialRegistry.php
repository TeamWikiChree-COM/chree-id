<?php
namespace App\Modules\Credential\Domain;

use App\Modules\Credential\Domain\Verifier\CredentialVerifier;
use LogicException;

class CredentialRegistry {
    /** @var array<string, CredentialVerifier> */
    private array $verifiers = [];

    /**
     * 二重登録は黙って上書きされると気づけないので、起動時に落とす。
     * 認証方式が意図せず差し替わるのは事故なので、動く前に止める。
     */
    public function register(CredentialVerifier $verifier): void {
        $key = $verifier->type()->value;
        if (isset($this->verifiers[$key])) throw new LogicException("{$key} は既に登録されています");

        $this->verifiers[$key] = $verifier;
    }

    public function get(CredentialType $type): ?CredentialVerifier {
        return $this->verifiers[$type->value] ?? null;
    }

    /** @return array<string, CredentialVerifier> */
    public function all(): array {
        return $this->verifiers;
    }
}
