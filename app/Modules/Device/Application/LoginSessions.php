<?php
namespace App\Modules\Device\Application;

use App\Modules\Device\Domain\DeviceLabel;
use App\Modules\Device\Infrastructure\LoginSessionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ログイン中の端末の記録と失効。
 *
 * 実体のセッションは Laravel の sessions テーブルにある。こちらはその控えなので、
 * 失効させるときは必ず両方消すこと。控えだけ消しても、そのセッションは生きている。
 */
class LoginSessions {
    /**
     * いま処理中のリクエストのセッションを記録する。
     *
     * 既にあれば最終利用だけ進める。セッションIDはログインのたびに再生成されるので、
     * 行は新しく増える。古いほうは sessions 側が消えた時点で一覧から落ちる。
     *
     * @param Request $request
     * @param string $accountId アカウントID (ULID)
     * @return void
     */
    public function track(Request $request, string $accountId): void {
        LoginSessionModel::query()->updateOrCreate(
            ['id' => $request->session()->getId()],
            [
                'auth_identity_id' => $accountId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_active_at' => now(),
            ],
        );
    }

    /**
     * ログイン中の端末を新しい順に返す。
     *
     * 実体が残っているものだけを返す。控えが残っているだけの行は、
     * 既にログアウト済みか期限切れなので見せない。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $currentId いま使っているセッションID
     * @return list<array{id: string, label: string, ipAddress: string|null, lastActiveAt: string|null, isCurrent: bool}>
     */
    public function listFor(string $accountId, string $currentId): array {
        $alive = DB::table('sessions')->pluck('id')->all();

        $rows = LoginSessionModel::query()
            ->where('auth_identity_id', $accountId)
            ->whereIn('id', $alive)
            ->orderByDesc('last_active_at')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => $row->id,
                'label' => DeviceLabel::from($row->user_agent),
                'ipAddress' => $row->ip_address,
                'lastActiveAt' => $row->last_active_at?->toDateTimeString(),
                'isCurrent' => $row->id === $currentId,
            ];
        }

        return $result;
    }

    /**
     * 指定した1台を切る。
     *
     * **他人のセッションを切れないよう、必ずアカウントIDで絞る。**
     * セッションIDは当てにくいが、当てられたら誰でも切れるようでは困る。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $sessionId 切るセッションID
     * @return bool 実際に切れたか
     */
    public function revoke(string $accountId, string $sessionId): bool {
        $owned = LoginSessionModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('id', $sessionId)
            ->exists();

        if (!$owned) return false;

        $this->forget($sessionId);

        return true;
    }

    /**
     * いま使っている端末以外をすべて切る。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $currentId 残すセッションID
     * @return int 切った台数
     */
    public function revokeOthers(string $accountId, string $currentId): int {
        $ids = LoginSessionModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('id', '!=', $currentId)
            ->pluck('id')
            ->all();

        $sessionIds = array_values(array_filter($ids, is_string(...)));
        foreach ($sessionIds as $id) {
            $this->forget($id);
        }

        return count($sessionIds);
    }

    /**
     * 控えと実体をまとめて消す。
     *
     * @param string $sessionId セッションID
     * @return void
     */
    public function forget(string $sessionId): void {
        DB::table('sessions')->where('id', $sessionId)->delete();
        LoginSessionModel::query()->where('id', $sessionId)->delete();
    }

    /**
     * 実体が無くなった控えを掃除する。
     *
     * @return int 消した件数
     */
    public function prune(): int {
        $alive = DB::table('sessions')->pluck('id')->all();
        $deleted = LoginSessionModel::query()->whereNotIn('id', $alive)->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}
