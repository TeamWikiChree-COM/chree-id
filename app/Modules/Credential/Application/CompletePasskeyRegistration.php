<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Infrastructure\Passkey\PasskeyCeremony;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyContext;
use App\Modules\Credential\Infrastructure\Passkey\PasskeySerializer;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyStore;
use RuntimeException;
use Throwable;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * ブラウザから返ってきた登録応答を検証して保存する
 */
class CompletePasskeyRegistration {
    public function __construct(
        private readonly PasskeyCeremony $ceremony,
        private readonly PasskeyContext $context,
        private readonly PasskeyStore $store,
        private readonly PasskeySerializer $serializer,
    ) {}

    /**
     * @param string $accountId アカウントID (ULID)
     * @param PublicKeyCredentialCreationOptions $options 発行時に保存しておいたもの
     * @param string $json ブラウザが返した credential の JSON
     * @param string|null $label 端末名
     * @return void
     * @throws RuntimeException 検証に失敗した場合
     */
    public function execute(string $accountId, PublicKeyCredentialCreationOptions $options, string $json, ?string $label = null): void {
        $credential = $this->serializer->toCredential($json);
        $response = $credential->response;

        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new RuntimeException(__('credential.passkey.invalid_response'));
        }

        try {
            $record = $this->ceremony->attestationValidator()->check($response, $options, $this->context->rpId());
        } catch (Throwable $e) {
            throw new RuntimeException(__('credential.passkey.verification_failed'), 0, $e);
        }

        $this->store->save($accountId, $record, $label);
    }
}
