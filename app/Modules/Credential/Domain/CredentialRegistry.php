<?php
namespace App\Modules\Credential\Domain;

use App\Modules\Credential\Domain\Verifier\CredentialVerifier;
use App\Support\Registry\Registry;

/**
 * 使える認証方式の一覧。
 *
 * @extends Registry<CredentialVerifier>
 */
class CredentialRegistry extends Registry {
    /**
     * @param CredentialVerifier $verifier 登録する認証方式の検証ロジッククラス
     * @return void
     */
    public function register(CredentialVerifier $verifier): void {
        $this->add($verifier->type()->value, $verifier);
    }

    /**
     * 指定した方式に対応する検証ロジッククラスを取得する
     *
     * @param CredentialType $type 取得したい認証方式
     * @return CredentialVerifier|null 検証ロジッククラス。未登録なら null
     */
    public function get(CredentialType $type): ?CredentialVerifier {
        return $this->find($type->value);
    }

    /**
     * 登録されている認証方式をすべて取得する
     *
     * @return array<string, CredentialVerifier> 登録されている認証方式の検証ロジッククラスをキーで引ける配列
     */
    public function all(): array {
        return $this->items();
    }
}
