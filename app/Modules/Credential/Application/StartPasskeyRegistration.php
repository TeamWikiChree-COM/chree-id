<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Infrastructure\Passkey\PasskeyContext;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyStore;
use App\Modules\Identity\Domain\AuthIdentity;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * パスキー登録のチャレンジを作る。
 *
 * 作った options はセッションに保存し、応答の検証時に同じものを使う。
 * 使い回すとリプレイを許すことになる。
 */
class StartPasskeyRegistration {
    public function __construct(
        private readonly PasskeyContext $context,
        private readonly PasskeyStore $store,
    ) {}

    /**
     * @param AuthIdentity $account
     * @return PublicKeyCredentialCreationOptions
     */
    public function execute(AuthIdentity $account): PublicKeyCredentialCreationOptions {
        // 同じ端末を二重登録させない
        $exclude = [];
        foreach ($this->store->credentialIdsOf($account->id) as $id) {
            $raw = base64_decode(strtr($id, '-_', '+/'), true);
            if ($raw !== false) $exclude[] = PublicKeyCredentialDescriptor::create('public-key', $raw);
        }

        return PublicKeyCredentialCreationOptions::create(
            $this->context->entity(),
            PublicKeyCredentialUserEntity::create(
                $account->email ?? $account->id,
                $account->id,
                $account->displayName ?? $account->id,
            ),
            random_bytes(32),
            [
                PublicKeyCredentialParameters::create('public-key', -7),   // ES256
                PublicKeyCredentialParameters::create('public-key', -257), // RS256
            ],
            AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            excludeCredentials: $exclude,
        );
    }
}
