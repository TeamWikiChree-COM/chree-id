<?php
namespace App\Modules\Credential\Domain;

use App\Modules\Credential\Domain\Verifier\CredentialVerifier;
use LogicException;

class CredentialRegistry {
    /** @var array<string, CredentialVerifier> */
    private array $verifiers = [];

    /**
     * 二重登録は黙って上書きされると気づけないので、起動時に落とす。
     * 認証方式が意図せず差し替わるのは事故なので、動く前に止める。
     * 
     * @param CredentialVerifier $verifier 登録する認証方式の検証ロジッククラス
     * @return void
     */
    public function register(CredentialVerifier $verifier): void {
        $key = $verifier->type()->value;
        if (isset($this->verifiers[$key])) throw new LogicException("{$key} は既に登録されています");

        $this->verifiers[$key] = $verifier;
    }

    /**
     * 指定した方式に対応する検証ロジッククラスを取得する
     * 
     * @param CredentialType $type 取得したい認証方式
     * @return CredentialVerifier|null 検証ロジッククラス。未登録なら null
     */
    public function get(CredentialType $type): ?CredentialVerifier {
        return $this->verifiers[$type->value] ?? null;
    }

    /** @return array<string, CredentialVerifier> */
    public function all(): array {
        return $this->verifiers;
    }
}
