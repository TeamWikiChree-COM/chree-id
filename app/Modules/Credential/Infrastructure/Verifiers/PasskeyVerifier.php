<?php
namespace App\Modules\Credential\Infrastructure\Verifiers;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerificationResult;
use App\Modules\Credential\Domain\Verifier\AbstractVerifier;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyCeremony;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyContext;
use App\Modules\Credential\Infrastructure\Passkey\PasskeySerializer;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyStore;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * パスキー (WebAuthn) の検証
 *
 * 端末の所持と生体認証/PINの組み合わせで既に多要素なので、単独で認証を完了してよい。
 */
class PasskeyVerifier extends AbstractVerifier {

    private readonly PasskeyCeremony $ceremony;
    private readonly PasskeyContext $context;
    private readonly PasskeyStore $store;
    private readonly PasskeySerializer $serializer;
    private readonly AuthIdentityRepository $accounts;

    public function __construct(PasskeyCeremony $ceremony, PasskeyContext $context, PasskeyStore $store, PasskeySerializer $serializer, AuthIdentityRepository $accounts) {
        $this->ceremony = $ceremony;
        $this->context = $context;
        $this->store = $store;
        $this->serializer = $serializer;
        $this->accounts = $accounts;
    }

    /**
     * @return CredentialType
     */
    public function type(): CredentialType {
        return CredentialType::PASSKEY;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isSufficient(): bool {
        return true;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param array<string, mixed> $input credential (JSON) と options
     * @return VerificationResult
     */
    public function verify(string $accountId, array $input): VerificationResult {
        $json = $input['credential'] ?? null;
        $options = $input['options'] ?? null;

        if (!\is_string($json) || !$options instanceof PublicKeyCredentialRequestOptions) return $this->failure();

        try {
            $credential = $this->serializer->toCredential($json);
        } catch (Throwable) {
            return $this->failure();
        }

        $response = $credential->response;
        if (!$response instanceof AuthenticatorAssertionResponse) return $this->failure();

        $source = $this->store->find($this->store->encodeId($credential->rawId));
        if ($source === null || $source->userHandle !== $accountId) return $this->failure();

        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->isSuspended()) return $this->failure();

        try {
            $updated = $this->ceremony->assertionValidator()->check(
                $source,
                $response,
                $options,
                $this->context->rpId(),
                $accountId,
            );
        } catch (Throwable) {
            return $this->failure();
        }

        $this->store->updateCounter($updated);

        return $this->success();
    }
}
