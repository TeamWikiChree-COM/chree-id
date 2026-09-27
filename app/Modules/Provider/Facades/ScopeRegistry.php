<?php
namespace App\Modules\Provider\Facades;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Provider\Domain\Claims\ClaimsResolver;
use App\Modules\Provider\Domain\Claims\ScopeRegistry as Registry;
use Illuminate\Support\Facades\Facade;

/**
 * OIDC の scope とクレームの対応 (Domain\Claims\ScopeRegistry) の Facade。
 *
 * @method static void register(ClaimsResolver $resolver)
 * @method static array<string, mixed> claimsFor(AuthIdentity $account, list<string> $scopes, ?string $serviceAccountId = null)
 *
 * @see Registry
 * @api
 */
class ScopeRegistry extends Facade {
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string {
        return Registry::class;
    }
}
