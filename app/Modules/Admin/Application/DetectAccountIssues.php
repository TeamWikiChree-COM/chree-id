<?php
namespace App\Modules\Admin\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * 種別と実体が食い違っているアカウントを探す。
 *
 * どれも「ユーザーアカウントとして扱うべきなのに、そうなっていない」状態で、
 * 直し方はいずれも昇格 (UserAccounts::ensure) になる。
 */
class DetectAccountIssues {
    /** origin は service なのに、束ねる人格を持っている */
    public const ORIGIN_BEHIND = 'origin_behind';

    /** origin は user なのに、束ねる人格を持っていない */
    public const MISSING_USER_ACCOUNT = 'missing_user_account';

    /** 引き取っていないのに、複数のサービスに紐付いている */
    public const MULTI_SERVICE = 'multi_service';

    /**
     * @return array<string, list<string>> アカウントID => 当てはまる問題
     */
    public function execute(): array {
        $issues = [];

        foreach ($this->originBehind() as $id) $issues[$id][] = self::ORIGIN_BEHIND;
        foreach ($this->missingUserAccount() as $id) $issues[$id][] = self::MISSING_USER_ACCOUNT;
        foreach ($this->multiService() as $id) $issues[$id][] = self::MULTI_SERVICE;

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function originBehind(): array {
        return $this->ids(DB::table('auth_identities')
            ->where('origin', 'service')
            ->whereIn('id', DB::table('user_accounts')->select('auth_identity_id')));
    }

    /**
     * @return list<string>
     */
    private function missingUserAccount(): array {
        return $this->ids(DB::table('auth_identities')
            ->where('origin', 'user')
            ->whereNotIn('id', DB::table('user_accounts')->select('auth_identity_id')));
    }

    /**
     * @return list<string>
     */
    private function multiService(): array {
        return $this->strings(DB::table('service_accounts')
            ->select('auth_identity_id')
            ->whereNotIn('auth_identity_id', DB::table('user_accounts')->select('auth_identity_id'))
            ->groupBy('auth_identity_id')
            ->havingRaw('COUNT(DISTINCT client_id) > 1')
            ->pluck('auth_identity_id')
            ->all());
    }

    /**
     * @param Builder $query
     * @return list<string>
     */
    private function ids(Builder $query): array {
        return $this->strings($query->pluck('id')->all());
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private function strings(array $values): array {
        return array_values(array_filter($values, is_string(...)));
    }
}
