<?php
namespace App\Modules\Credential\Domain;

/**
 * 検証に成功した認証要素ひとつ分。
 *
 * sufficient は Verifier の isSufficient() をそのまま持ち回るためのもの。
 */
readonly class VerifiedFactor {
    public CredentialType $type;
    public bool $sufficient;

    /**
     * @param CredentialType $type
     * @param bool $sufficient 単独で多要素を満たすか
     */
    public function __construct(CredentialType $type, bool $sufficient) {
        $this->type = $type;
        $this->sufficient = $sufficient;
    }
}
