<?php
namespace App\Modules\Provider\Domain\Claims;

use App\Modules\Identity\Domain\AuthIdentity;
use LogicException;

/**
 * scope とクレームの対応表。
 *
 * どの scope で何が渡るかを1箇所で一覧できるようにしている (プライバシー要件の監査のため)。
 */
class ScopeRegistry {
    /** @var array<string, ClaimsResolver> */
    private array $resolvers = [];

    /**
     * @param ClaimsResolver $resolver
     * @return void
     */
    public function register(ClaimsResolver $resolver): void {
        $scope = $resolver->scope();
        if (isset($this->resolvers[$scope])) throw new LogicException("{$scope} は既に登録されています");

        $this->resolvers[$scope] = $resolver;
    }

    /**
     * 要求された scope の分だけクレームを集める。
     *
     * @param AuthIdentity $account
     * @param list<string> $scopes
     * @return array<string, mixed>
     */
    public function claimsFor(AuthIdentity $account, array $scopes): array {
        $claims = [];
        foreach ($scopes as $scope) {
            $resolver = $this->resolvers[$scope] ?? null;
            if ($resolver === null) continue;

            $claims = array_merge($claims, $resolver->resolve($account));
        }

        return $claims;
    }
}
