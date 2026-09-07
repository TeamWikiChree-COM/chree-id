<?php
namespace App\Modules\Credential\Infrastructure\Verifiers;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerificationResult;
use App\Modules\Credential\Domain\Verifier\AbstractVerifier;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\Totp;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * TOTP による2要素目の検証
 *
 * credentials.secret には base32 の秘密鍵を暗号化して入れる。
 * コード生成に元の値が要るので、パスワードのようにハッシュ化してはいけない。
 */
class TotpVerifier extends AbstractVerifier {
    public function __construct(private readonly Totp $totp) {}

    /**
     * @return CredentialType
     */
    public function type(): CredentialType {
        return CredentialType::TOTP;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param array<string, mixed> $input code キーに6桁の数字
     * @return VerificationResult
     */
    public function verify(string $accountId, array $input): VerificationResult {
        $code = $input['code'] ?? null;
        if (!\is_string($code) || $code === '') return $this->failure();

        $row = CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::TOTP)
            ->first();
        if ($row === null || $row->secret === null) return $this->failure();

        try {
            $secret = Crypt::decryptString($row->secret);
        } catch (DecryptException) {
            return $this->failure();
        }

        if (!$this->totp->verify($secret, $code)) return $this->failure();

        $row->forceFill(['last_used_at' => now()])->save();

        return $this->success();
    }
}
