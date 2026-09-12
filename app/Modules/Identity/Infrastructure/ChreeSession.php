<?php
namespace App\Modules\Identity\Infrastructure;

use App\Modules\Audit\Application\LoginHistory;
use Illuminate\Http\Request;

/**
 * ログイン状態の保持。
 *
 * Laravel 標準の認証は users テーブル前提なので使わず、
 * ChreeID のアカウントIDをセッションに置くだけにする。
 */
class ChreeSession {
    private const KEY = 'chreeid.account_id';

    private readonly Request $request;
    private readonly LoginHistory $history;

    public function __construct(Request $request, LoginHistory $history) {
        $this->request = $request;
        $this->history = $history;
    }

    /**
     * ログイン状態にする。
     *
     * セッション固定攻撃を避けるため、ここで必ずIDを再生成する。
     *
     * **履歴もここで残す。** ログインの成立点は全経路がここを通るので、
     * 呼び出し側に記録を任せると、経路を足したときに取りこぼす。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $method どう入ったか。LoginMethod の値
     * @return void
     */
    public function login(string $accountId, string $method): void {
        $this->request->session()->regenerate();
        $this->request->session()->put(self::KEY, $accountId);

        $this->history->record($accountId, $method);
    }

    /**
     * @return void
     */
    public function logout(): void {
        $this->request->session()->forget(self::KEY);
        $this->request->session()->regenerate();
    }

    /**
     * @return string|null ログインしていなければ null
     */
    public function accountId(): ?string {
        $id = $this->request->session()->get(self::KEY);

        return is_string($id) ? $id : null;
    }

    /**
     * @return bool
     */
    public function isLoggedIn(): bool {
        return $this->accountId() !== null;
    }
}
