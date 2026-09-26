<?php
namespace App\Modules\Provider\Domain\Claims;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Support\Registry\Registry;

/**
 * scope とクレームの対応表。
 *
 * どの scope で何が渡るかを1箇所で一覧できるようにしている (プライバシー要件の監査のため)。
 *
 * @extends Registry<ClaimsResolver>
 */
class ScopeRegistry extends Registry {
    /**
     * @param ClaimsResolver $resolver
     * @return void
     */
    public function register(ClaimsResolver $resolver): void {
        $this->add($resolver->scope(), $resolver);
    }

    /**
     * 要求された scope の分だけクレームを集める。
     *
     * @param AuthIdentity $account
     * @param list<string> $scopes
     * @param string|null $serviceAccountId 渡す先のサービスアカウント
     * @return array<string, mixed>
     */
    public function claimsFor(AuthIdentity $account, array $scopes, ?string $serviceAccountId = null): array {
        $claims = [];
        foreach ($scopes as $scope) {
            $resolver = $this->find($scope);
            if ($resolver === null) continue;

            $claims = array_merge($claims, $resolver->resolve($account, $serviceAccountId));
        }

        return $claims;
    }
}
