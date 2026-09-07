<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Infrastructure\Passkey\PasskeyContext;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * パスキーでログインするチャレンジを作る。
 *
 * allowCredentials を空にして、どのアカウントかは端末に選ばせる (discoverable credential)。
 * メールアドレスを先に聞かずに済み、アカウントの存在も漏れない。
 */
class StartPasskeyLogin {
    public function __construct(private readonly PasskeyContext $context) {}

    /**
     * @return PublicKeyCredentialRequestOptions
     */
    public function execute(): PublicKeyCredentialRequestOptions {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->context->rpId(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        );
    }
}
