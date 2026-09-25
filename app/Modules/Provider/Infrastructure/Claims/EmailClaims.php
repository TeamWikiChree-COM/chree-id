<?php
namespace App\Modules\Provider\Infrastructure\Claims;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Domain\Claims\ClaimsResolver;

/**
 * scope=email で渡すクレーム
 *
 * サービスごとに割り当てたアドレスがあればそれを渡す。割り当てられるのは本人のものと
 * 確かめたアドレスだけなので (ServiceEmails)、確認済みとして渡してよい。
 */
class EmailClaims implements ClaimsResolver {
    /** @return string */
    public function scope(): string {
        return 'email';
    }

    /**
     * @param AuthIdentity $account
     * @param string|null $serviceAccountId
     * @return array<string, mixed>
     */
    public function resolve(AuthIdentity $account, ?string $serviceAccountId): array {
        $assigned = $serviceAccountId === null
            ? null
            : ServiceAccountModel::query()->whereKey($serviceAccountId)->value('email');

        if ($assigned !== null) return ['email' => $assigned, 'email_verified' => true];

        return [
            'email' => $account->email,
            'email_verified' => $account->isEmailVerified(),
        ];
    }
}
