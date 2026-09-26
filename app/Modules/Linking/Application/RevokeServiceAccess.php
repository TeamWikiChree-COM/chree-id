<?php
namespace App\Modules\Linking\Application;

use App\Modules\Provider\Application\RevokeAccessTokens;

/**
 * サービスへの連携を解除する。
 *
 * 発行済みのアクセストークンを失効させるだけで、service_subject_ids は消さない。
 * sub はサービスごとに採番していて、消して作り直すと値が変わる。
 * 向こうから見ると別人になり、繋ぎ直しても元のアカウントに戻れなくなる。
 */
class RevokeServiceAccess {
    private readonly RevokeAccessTokens $tokens;

    public function __construct(RevokeAccessTokens $tokens) {
        $this->tokens = $tokens;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $clientId サービスの client_id
     * @return int 失効させたトークンの数
     */
    public function execute(string $accountId, string $clientId): int {
        return $this->tokens->forClient($accountId, $clientId);
    }
}
