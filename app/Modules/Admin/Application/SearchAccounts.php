<?php
namespace App\Modules\Admin\Application;

use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * 管理画面のアカウント検索。
 *
 * 全件を1ページに出していると、数が増えたときに目当ての行へ辿り着けない。
 */
class SearchAccounts {
    public const PER_PAGE = 50;

    public const KINDS = ['user', 'service'];

    public const STATUSES = ['active', 'suspended', 'deleted'];

    /**
     * @param string $query メール・表示名・ID の部分一致。空なら絞らない
     * @param string|null $kind user / service
     * @param string|null $status active / suspended / deleted
     * @param string|null $clientId 紐付いているサービス
     * @param int $page 1 始まり
     * @return LengthAwarePaginator<int, AuthIdentityModel>
     */
    public function execute(string $query, ?string $kind, ?string $status, ?string $clientId, int $page): LengthAwarePaginator {
        $builder = AuthIdentityModel::query();

        if ($query !== '') $this->matchText($builder, $query);
        if ($kind !== null) $builder->where('origin', $kind);
        if ($status !== null) $this->matchStatus($builder, $status);

        if ($clientId !== null) {
            $builder->whereIn('id', fn (QueryBuilder $sub) => $sub->select('auth_identity_id')->from('service_accounts')->where('client_id', $clientId));
        }

        return $builder
            ->orderByDesc('created_at')
            // 同じ秒に作られた分の並びが揺れないよう、ULID で決着を付ける
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * サービス側の識別子でも引けるようにする。問い合わせはたいていそちらで来る。
     *
     * @param Builder<AuthIdentityModel> $builder
     * @param string $query
     */
    private function matchText(Builder $builder, string $query): void {
        $like = '%' . addcslashes(mb_strtolower($query), '%_\\') . '%';

        $builder->where(fn (Builder $w) => $w
            ->whereRaw('LOWER(email) LIKE ?', [$like])
            ->orWhereRaw('LOWER(display_name) LIKE ?', [$like])
            ->orWhere('id', mb_strtolower($query))
            ->orWhereIn('id', fn (QueryBuilder $sub) => $sub->select('auth_identity_id')->from('service_accounts')
                ->where('service_user_id', $query)
                ->orWhere('sub', $query)));
    }

    /**
     * @param Builder<AuthIdentityModel> $builder
     * @param string $status
     */
    private function matchStatus(Builder $builder, string $status): void {
        match ($status) {
            'active' => $builder->whereNull('suspended_at')->whereNull('deleted_at'),
            'suspended' => $builder->whereNotNull('suspended_at')->whereNull('deleted_at'),
            'deleted' => $builder->whereNotNull('deleted_at'),
            default => null,
        };
    }
}
