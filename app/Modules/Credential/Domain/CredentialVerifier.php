<?php
namespace App\Modules\Credential\Domain;

// 認証方式ごとの検証ロジック
interface CredentialVerifier {
    // 認証タイプ
    public function type(): CredentialType;

    /**
     * 入力の検証ロジック
     * 
     * @param string $accountId アカウントID (ULID)
     * @param array<string, mixed> $input 認証方式ごとの入力値
     * @return VerificationResult
     */
    public function verify(string $accountId, array $input): VerificationResult;

    // 単独認証のみとするか (追加認証はいらないとするか、例: passkeyならtrue, passwordならfalse)
    public function isSufficient(): bool;
}
