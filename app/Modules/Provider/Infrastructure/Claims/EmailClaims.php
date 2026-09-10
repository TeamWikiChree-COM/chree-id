<?php
namespace App\Modules\Provider\Infrastructure\Claims;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Provider\Domain\Claims\ClaimsResolver;

/**
 * scope=email で渡すクレーム
 */
class EmailClaims implements ClaimsResolver {
    /** @return string */
    public function scope(): string {
        return 'email';
    }

    /**
     * @param AuthIdentity $account
     * @return array<string, mixed>
     */
    public function resolve(AuthIdentity $account): array {
        return [
            'email' => $account->email,
            'email_verified' => $account->isEmailVerified(),
        ];
    }
}
