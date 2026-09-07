<?php
namespace App\Modules\Identity\Infrastructure;

use Illuminate\Http\Request;

/**
 * ログイン状態の保持。
 *
 * Laravel 標準の認証は users テーブル前提なので使わず、
 * ChreeID のアカウントIDをセッションに置くだけにする。
 */
class ChreeSession {
    private const KEY = 'chreeid.account_id';

    public function __construct(private readonly Request $request) {}

    /**
     * ログイン状態にする。
     *
     * セッション固定攻撃を避けるため、ここで必ずIDを再生成する。
     *
     * @param string $accountId アカウントID (ULID)
     * @return void
     */
    public function login(string $accountId): void {
        $this->request->session()->regenerate();
        $this->request->session()->put(self::KEY, $accountId);
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
