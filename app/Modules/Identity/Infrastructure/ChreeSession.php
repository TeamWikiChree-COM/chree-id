<?php
namespace App\Modules\Identity\Infrastructure;

use App\Modules\Audit\Application\AuditLog;
use App\Support\Session\SignedInMarker;
use App\Modules\Audit\Domain\AuditAction;
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
    private readonly AuditLog $audit;
    private readonly SignedInMarker $marker;

    public function __construct(Request $request, AuditLog $audit, SignedInMarker $marker) {
        $this->request = $request;
        $this->audit = $audit;
        $this->marker = $marker;
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

        $this->audit->record(AuditAction::LOGIN_SUCCEEDED, $accountId, ['method' => $method]);
    }

    /**
     * @return void
     */
    public function logout(): void {
        $this->request->session()->forget(self::KEY);
        $this->request->session()->regenerate();

        // 意図したログアウトなので、勝手に消えた扱いにしない (DetectSessionLoss)
        $this->marker->forget();
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
