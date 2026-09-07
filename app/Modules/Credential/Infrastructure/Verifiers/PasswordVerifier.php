<?php
namespace App\Modules\Credential\Infrastructure\Verifiers;

use App\Modules\Credential\Domain\Verifier\AbstractVerifier;
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

    /**
     * パスワードを直接検証する。
     * 
     * ログインは verify() を通るが、
     * 再認証（メール変更前の確認、パスワードリセット時の確認など）はここを直接呼ぶ場合もあると想定する。
     * 
     * @param string $accountId アカウントID
     * @param ?string $password パスワード
     * @return VerificationResult
     */
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