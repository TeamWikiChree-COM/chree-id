<?php
namespace App\Modules\Provider\Facades;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Provider\Domain\Claims\ClaimsResolver;
use App\Modules\Provider\Domain\Claims\ScopeRegistry as Registry;
use Illuminate\Support\Facades\Facade;

/**
 * OIDC の scope とクレームの対応を static の形で触るための窓口。プラグインはこれで scope を足す。
 *
 * 一覧そのものは Domain\Claims\ScopeRegistry で、コンテナに1つだけある。
 * 本物の static にしないのは、テストのたびにアプリを作り直しても一覧が残り、二重登録になるため。
 *
 * 足した scope をサービスが求められるようにするには、そのサービス (oauth_clients) に scope を許しておく。
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
