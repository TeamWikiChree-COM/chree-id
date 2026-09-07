<?php
namespace App\Modules\Credential\Infrastructure\Verifiers;

use App\Modules\Credential\Domain\AbstractVerifier;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerificationResult;
use Illuminate\Support\Facades\DB;

/**
 * パスワードの認証ロジックとか
 */
class PasswordVerifier extends AbstractVerifier {
    public function type(): CredentialType {
        return CredentialType::PASSWORD;
    }

    public function verify(string $accountId, array $input): VerificationResult {
        return $this->verifyPassword($accountId, $input['password'] ?? null);
    }

    public function verifyPassword(string $accountId, ?string $password): VerificationResult {
        if (!is_string($password) || $password === '') return $this->failure();

        $row = DB::table('credentials')
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::PASSWORD->value)
            ->first();
        
        // パスワードが間違ってる、またはそもそもパスワードが設定されていない
        if ($row === null || !password_verify($password, $row->secret)) 
            return $this->failure();

        return $this->success();
    }
}