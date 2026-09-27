<?php
namespace App\Modules\Credential\Facades;

use App\Modules\Credential\Domain\CredentialRegistry as Registry;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\Verifier\CredentialVerifier;
use Illuminate\Support\Facades\Facade;

/**
 * 認証方式の一覧 (Domain\CredentialRegistry) の Facade。
 *
 * @method static void register(CredentialVerifier $verifier)
 * @method static CredentialVerifier|null get(CredentialType $type)
 * @method static array<string, CredentialVerifier> all()
 *
 * @see Registry
 * @api
 */
class CredentialRegistry extends Facade {
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string {
        return Registry::class;
    }
}
