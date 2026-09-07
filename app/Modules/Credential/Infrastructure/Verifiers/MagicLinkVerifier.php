<?php
namespace App\Modules\Credential\Infrastructure\Verifiers;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerificationResult;
use App\Modules\Credential\Domain\Verifier\AbstractVerifier;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;

/**
 * マジックリンク認証
 *
 * credentials の行は「メールログインを有効にした」という記録だけで、secret を持たない。
 * 実際に照合するトークンは one_time_tokens 側にある (寿命が数十分なので credentials に混ぜない)。
 *
 * メールが登録されているだけでログインできてしまうと
 * 「credentials が0件 = ログインする材料が無い」が崩れるため、有効化の行を必須にしている。
 */
class MagicLinkVerifier extends AbstractVerifier {
    /**
     * @return CredentialType
     */
    public function type(): CredentialType {
        return CredentialType::MAGIC_LINK;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param array<string, mixed> $input token キーにメールで送った平文トークン
     * @return VerificationResult
     */
    public function verify(string $accountId, array $input): VerificationResult {
        $token = $input['token'] ?? null;
        if (!\is_string($token) || $token === '') return $this->failure();

        if (!$this->isEnabled($accountId)) return $this->failure();

        return $this->consumeToken($accountId, $token);
    }

    /**
     * メールログインが有効になっているか。
     *
     * @param string $accountId アカウントID (ULID)
     * @return bool
     */
    private function isEnabled(string $accountId): bool {
        return CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::MAGIC_LINK)
            ->exists();
    }

    /**
     * トークンを照合し、成功したら使用済みにする。
     *
     * 平文は保存していないので、ハッシュに変換してから引く。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $token メールで送った平文トークン
     * @return VerificationResult
     */
    private function consumeToken(string $accountId, string $token): VerificationResult {
        $row = OneTimeTokenModel::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('chree_account_id', $accountId)
            ->where('purpose', OneTimeTokenModel::PURPOSE_LOGIN)
            ->first();

        if ($row === null || !$row->isUsable()) return $this->failure();

        // 使用済みにする。削除しないのは、同じトークンの二重投入を検知できるようにするため
        $row->forceFill(['used_at' => now()])->save();

        return $this->success();
    }
}
