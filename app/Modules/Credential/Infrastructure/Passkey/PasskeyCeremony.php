<?php
namespace App\Modules\Credential\Infrastructure\Passkey;

use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;

/**
 * WebAuthn の検証器を組み立てる。
 *
 * ライブラリ側の初期化が煩雑なので、ここに閉じ込めて他から見えないようにする。
 */
class PasskeyCeremony {
    public function __construct(private readonly PasskeyContext $context) {}

    /**
     * 登録 (attestation) の検証器
     *
     * @return AuthenticatorAttestationResponseValidator
     */
    public function attestationValidator(): AuthenticatorAttestationResponseValidator {
        return AuthenticatorAttestationResponseValidator::create($this->factory()->creationCeremony());
    }

    /**
     * ログイン (assertion) の検証器
     *
     * @return AuthenticatorAssertionResponseValidator
     */
    public function assertionValidator(): AuthenticatorAssertionResponseValidator {
        return AuthenticatorAssertionResponseValidator::create($this->factory()->requestCeremony());
    }

    /**
     * @return CeremonyStepManagerFactory
     */
    private function factory(): CeremonyStepManagerFactory {
        $factory = new CeremonyStepManagerFactory();
        $factory->setSecuredRelyingPartyId([$this->context->rpId()]);

        return $factory;
    }
}
