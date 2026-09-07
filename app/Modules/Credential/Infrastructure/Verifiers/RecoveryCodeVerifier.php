<?php
namespace App\Modules\Credential\Infrastructure\Verifiers;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerificationResult;
use App\Modules\Credential\Domain\Verifier\AbstractVerifier;
use App\Modules\Credential\Infrastructure\CredentialModel;

/**
 * 復旧コードの検証
 *
 * TOTP の端末を失くしたときに、2要素目の代わりとして使う。
 * 一度使ったコードは削除して再利用できないようにする。
 */
class RecoveryCodeVerifier extends AbstractVerifier {
    /**
     * @return CredentialType
     */
    public function type(): CredentialType {
        return CredentialType::RECOVERY_CODE;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param array<string, mixed> $input code キーに復旧コード
     * @return VerificationResult
     */
    public function verify(string $accountId, array $input): VerificationResult {
        $code = $input['code'] ?? null;
        if (!\is_string($code) || $code === '') return $this->failure();

        $row = CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::RECOVERY_CODE)
            ->where('secret', hash('sha256', \strtolower(\trim($code))))
            ->first();

        if ($row === null) return $this->failure();

        // 使い捨て。残しておくと同じコードで何度も入れてしまう
        $row->delete();

        return $this->success();
    }
}
