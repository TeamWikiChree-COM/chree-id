<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Credential\Infrastructure\OneTimeTokenModel;

/**
 * メールのリンクからアカウントを引き当てて、要素として積む。
 *
 * リンクにはアカウントIDを載せないので、先にトークンから引く必要がある。
 * 照合と使用済み化そのものは MagicLinkVerifier の担当。
 */
class ConsumeMagicLink {
    public function __construct(private readonly VerifyCredential $verify) {}

    /**
     * @param string $token メールに載せた平文トークン
     * @param VerifiedFactors $factors 成功した要素の積み先
     * @return string|null 引き当てたアカウントID。使えないリンクなら null
     */
    public function execute(string $token, VerifiedFactors $factors): ?string {
        $row = OneTimeTokenModel::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('purpose', OneTimeTokenModel::PURPOSE_LOGIN)
            ->first();

        if ($row === null) return null;

        $result = $this->verify->execute(
            $row->auth_identity_id,
            CredentialType::MAGIC_LINK,
            ['token' => $token],
            $factors,
        );

        return $result->isSuccess() ? $row->auth_identity_id : null;
    }
}
