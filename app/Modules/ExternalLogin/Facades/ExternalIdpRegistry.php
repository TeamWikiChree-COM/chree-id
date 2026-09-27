<?php
namespace App\Modules\ExternalLogin\Facades;

use App\Modules\ExternalLogin\Domain\ExternalIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry as Registry;
use Illuminate\Support\Facades\Facade;

/**
 * 外部 IdP の一覧を static の形で触るための窓口。プラグインはこれで IdP を追加する。
 *
 * 一覧そのものは Domain\ExternalIdpRegistry で、コンテナに1つだけある。
 * 本物の static にしないのは、テストのたびにアプリを作り直しても一覧が残り、同じ IdP の二重登録になるため。
 * Facade ならコンテナの1つを指すので、作り直せば一緒に消える。
 *
 * 本体を変えても、ここに書いたメソッドは互換性を保つ。
 *
 * @method static void register(ExternalIdp $provider)
 * @method static ExternalIdp|null get(string $name)
 * @method static list<string> names()
 * @method static list<string> usableNames()
 * @method static array<string, array{label: string, icon: string, family: string}> displays(string $locale)
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
