<?php
namespace App\Modules\Audit\Application;

use App\Modules\Audit\Infrastructure\LoginEventModel;
use App\Modules\Device\Domain\DeviceLabel;
use Illuminate\Http\Request;

/**
 * 本人が見るログイン履歴。
 *
 * 「いま入っている端末」(LoginSessions) とは別物。こちらはセッションが切れても残す。
 * 残さないと、身に覚えのないログインに後から気付けない。
 */
class LoginHistory {
    /** 画面に出す件数。遡って調べる用途ではないので、直近だけで足りる */
    public const RECENT = 20;

    /** 残す日数。過ぎたものは掃除で消す */
    public const KEEP_DAYS = 90;

    private readonly Request $request;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    /**
     * ログインの出来事を1件記録する。
     *
     * **記録の失敗でログインを止めない。** 履歴は後から読むためのもので、
     * 認証の成否とは関係がない。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $method 'password' や 'oauth:google' など
     * @param bool $succeeded 成立したか
     * @return void
     */
    public function record(string $accountId, string $method, bool $succeeded = true): void {
        LoginEventModel::create([
            'auth_identity_id' => $accountId,
            'method' => mb_substr($method, 0, 32),
            'succeeded' => $succeeded,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * 直近の履歴を新しい順に返す。
     *
     * @param string $accountId アカウントID (ULID)
     * @return list<array{id: string, method: string, succeeded: bool, label: string, ipAddress: string|null, at: string|null}>
     */
    public function listFor(string $accountId): array {
        $rows = LoginEventModel::query()
            ->where('auth_identity_id', $accountId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => $row->id,
                'method' => $row->method,
                'succeeded' => $row->succeeded,
                'label' => DeviceLabel::from($row->user_agent),
                'ipAddress' => $row->ip_address,
                'at' => $row->created_at?->toDateTimeString(),
            ];
        }

        return $result;
    }

    /**
     * 古い履歴を消す。
     *
     * @return int 消した件数
     */
    public function prune(): int {
        $deleted = LoginEventModel::query()
            ->where('created_at', '<', now()->subDays(self::KEEP_DAYS))
            ->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}
