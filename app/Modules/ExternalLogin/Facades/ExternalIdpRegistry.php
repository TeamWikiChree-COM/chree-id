<?php
namespace App\Modules\ExternalLogin\Facades;

use App\Modules\ExternalLogin\Domain\ExternalIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry as Registry;
use Illuminate\Support\Facades\Facade;

/**
 * 外部 IdP の一覧 (Domain\ExternalIdpRegistry) の Facade。
 *
 * @method static void register(ExternalIdp $provider)
 * @method static ExternalIdp|null get(string $name)
 * @method static list<string> names()
 * @method static list<string> usableNames()
 * @method static array<string, array{label: string, icon: string, family: string, svg: string|null}> displays(string $locale)
 *
 * @see Registry
 * @api
 */
class ExternalIdpRegistry extends Facade {
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string {
        return Registry::class;
    }
}
