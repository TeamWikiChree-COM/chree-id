<?php
namespace App\Modules\Provider\Infrastructure\Claims;

use App\Modules\Identity\Domain\ChreeAccount;
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
     * @param ChreeAccount $account
     * @return array<string, mixed>
     */
    public function resolve(ChreeAccount $account): array {
        return [
            'email' => $account->email,
            'email_verified' => $account->isEmailVerified(),
        ];
    }
}
