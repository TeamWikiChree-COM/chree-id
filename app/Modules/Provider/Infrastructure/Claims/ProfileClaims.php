<?php
namespace App\Modules\Provider\Infrastructure\Claims;

use App\Modules\Identity\Domain\ChreeAccount;
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
     * @param ChreeAccount $account
     * @return array<string, mixed>
     */
    public function resolve(ChreeAccount $account): array {
        return ['name' => $account->displayName];
    }
}
