<?php
namespace App\Modules\Credential\Facades;

use App\Modules\Credential\Domain\CredentialRegistry as Registry;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\Verifier\CredentialVerifier;
use Illuminate\Support\Facades\Facade;

/**
 * 認証方式の一覧を static の形で触るための窓口。
 *
 * 一覧そのものは Domain\CredentialRegistry で、コンテナに1つだけある。
 * 本物の static にしないのは、テストのたびにアプリを作り直しても一覧が残り、二重登録になるため。
 *
 * 追加できるのは検証のしかたまで。認証が成り立ったかの判断は AuthenticationPolicy が持ち、ここからは変えられない。
 * 方式の種類は CredentialType (本体の列挙型) で決まっているので、新しい種類を追加するには本体の変更も要る。
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
