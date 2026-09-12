<?php
namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Audit\Infrastructure\AuditEventModel;
use App\Modules\Device\Domain\DeviceLabel;
use Illuminate\Http\Request;

/**
 * 監査ログ。
 *
 * 本人が「自分の身に何が起きたか」を追うためのもので、運営が追う記録も同じ表に乗る。
 * 分けると、本人に見せる履歴と運営が見る記録が食い違う。
 *
 * **記録の失敗で本処理を止めない。** ログは後から読むためのもので、
 * 操作そのものの成否とは関係がない。
 */
class AuditLog {
    /** 本人の画面に出す件数 */
    public const RECENT = 50;

    /** 残す日数。過ぎたものは掃除で消す */
    public const KEEP_DAYS = 180;

    private readonly Request $request;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    /**
     * 出来事を1件残す。
     *
     * @param AuditAction $action 何が起きたか
     * @param string|null $accountId 誰についての記録か (ULID)
     * @param array<string, mixed> $context 方式や件数など、行ごとに形の違う付随情報
     * @param bool $succeeded 失敗の記録なら false
     * @param string|null $actorId 実行者。省略すると本人の操作として扱う
     * @return void
     */
    public function record(
        AuditAction $action,
        ?string $accountId,
        array $context = [],
        bool $succeeded = true,
        ?string $actorId = null,
    ): void {
        AuditEventModel::create([
            'auth_identity_id' => $accountId,
            'actor_id' => $actorId ?? $accountId,
            'action' => $action,
            'succeeded' => $succeeded,
            'context' => $context === [] ? null : $context,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * 本人の記録を新しい順に返す。
     *
     * @param string $accountId アカウントID (ULID)
     * @param bool $loginsOnly ログインの記録だけに絞るか
     * @param int $limit 最大件数
     * @return list<array{id: string, action: string, succeeded: bool, label: string, ipAddress: string|null, at: string|null, context: array<string, mixed>, byOther: bool}>
     */
    public function listFor(string $accountId, bool $loginsOnly = false, int $limit = self::RECENT): array {
        $query = AuditEventModel::query()
            ->where('auth_identity_id', $accountId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($loginsOnly) {
            $query->whereIn('action', [AuditAction::LOGIN_SUCCEEDED->value, AuditAction::LOGIN_FAILED->value]);
        }

        return $this->describe(array_values($query->get()->all()), $accountId);
    }

    /**
     * 全アカウントの記録を新しい順に返す (運営向け)。
     *
     * @param int $limit 最大件数
     * @return list<array{id: string, action: string, succeeded: bool, label: string, ipAddress: string|null, at: string|null, context: array<string, mixed>, byOther: bool}>
     */
    public function recent(int $limit = 200): array {
        $rows = AuditEventModel::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();

        return $this->describe(array_values($rows), null);
    }

    /**
     * 古い記録を消す。
     *
     * @return int 消した件数
     */
    public function prune(): int {
        $deleted = AuditEventModel::query()
            ->where('created_at', '<', now()->subDays(self::KEEP_DAYS))
            ->delete();

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * @param list<AuditEventModel> $rows
     * @param string|null $viewerId 見ている本人。自分以外がやった操作に印を付けるのに使う
     * @return list<array{id: string, action: string, succeeded: bool, label: string, ipAddress: string|null, at: string|null, context: array<string, mixed>, byOther: bool}>
     */
    private function describe(array $rows, ?string $viewerId): array {
        $result = [];
        foreach ($rows as $row) {
            $context = is_array($row->context) ? $row->context : [];

            $result[] = [
                'id' => $row->id,
                'action' => $row->action->value,
                'succeeded' => $row->succeeded,
                'label' => DeviceLabel::from($row->user_agent),
                'ipAddress' => $row->ip_address,
                'at' => $row->created_at?->toDateTimeString(),
                'context' => $context,
                // 運営が代わりに操作した記録は、本人にそう見えないと誤解を生む
                'byOther' => $viewerId !== null && $row->actor_id !== null && $row->actor_id !== $viewerId,
            ];
        }

        return $result;
    }
}
