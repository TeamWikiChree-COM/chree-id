<?php
namespace App\Modules\Credential\Infrastructure\Verifiers;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerificationResult;
use App\Modules\Credential\Domain\Verifier\AbstractVerifier;
use App\Modules\Credential\Infrastructure\CredentialModel;

/**
 * パスワードの認証ロジックとか
 *
 * credentials.secret には bcrypt ハッシュが入る (不可逆)。
 */
class PasswordVerifier extends AbstractVerifier {
    /**
     * @return CredentialType
     */
    public function type(): CredentialType {
        return CredentialType::PASSWORD;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param array<string, mixed> $input password キーにパスワード文字列
     * @return VerificationResult
     */
    public function verify(string $accountId, array $input): VerificationResult {
        $password = $input['password'] ?? null;

        return $this->verifyPassword($accountId, is_string($password) ? $password : null);
    }

    /**
     * パスワードを直接検証する。
     *
     * ログインは verify() を通るが、再認証 (メール変更前の確認など) はここを直接呼ぶ。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string|null $password 入力されたパスワード
     * @return VerificationResult
     */
    public function verifyPassword(string $accountId, ?string $password): VerificationResult {
        if ($password === null || $password === '') return $this->failure();

        $row = CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::PASSWORD)
            ->first();

        // パスワードが間違ってる、またはそもそもパスワードが設定されていない
        if ($row === null || $row->secret === null) return $this->failure();
        if (!password_verify($password, $row->secret)) return $this->failure();

        $row->forceFill(['last_used_at' => now()])->save();

        return $this->success();
    }
}
