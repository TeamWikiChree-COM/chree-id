<?php
namespace App\Modules\Provider\Infrastructure\Claims;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Provider\Domain\Claims\ClaimsResolver;

/**
 * scope=profile で渡すクレーム
 */
class ProfileClaims implements ClaimsResolver {
    /** @return string */
    public function scope(): string {
        return 'profile';
    }

    /**
     * @param AuthIdentity $account
     * @return array<string, mixed>
     */
    public function resolve(AuthIdentity $account): array {
        return ['name' => $account->displayName];
    }
}
