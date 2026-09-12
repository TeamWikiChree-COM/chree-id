<?php
namespace App\Modules\Device\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Device\Application\LoginSessions;
use App\Modules\Device\Application\TrustedDevices;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 端末の管理画面。
 *
 * ログイン中のセッションと、2段階目を省略してよい端末の2つを扱う。
 * 似ているが別物で、ログアウトしても信頼は残る。混ぜないこと。
 */
class DeviceController {
    private readonly ChreeSession $session;
    private readonly LoginSessions $sessions;
    private readonly TrustedDevices $trustedDevices;
    private readonly AuditLog $audit;

    public function __construct(ChreeSession $session, LoginSessions $sessions, TrustedDevices $trustedDevices, AuditLog $audit) {
        $this->session = $session;
        $this->sessions = $sessions;
        $this->trustedDevices = $trustedDevices;
        $this->audit = $audit;
    }

    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        return Inertia::render('Settings/Devices', [
            'sessions' => $this->sessions->listFor($accountId, $request->session()->getId()),
            'trustedDevices' => $this->trustedDevices->listFor($accountId, $this->trustedDevices->tokenFrom($request)),
        ]);
    }

    /**
     * ログイン中の1台を切る。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function revokeSession(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate(['id' => ['required', 'string']]);

        $id = $request->string('id')->toString();

        // 自分のセッションを選んだ場合はログアウトそのもの。切ったあと画面を出す先がない
        if ($id === $request->session()->getId()) {
            $this->sessions->forget($id);
            $this->session->logout();

            return redirect('/login');
        }

        $this->sessions->revoke($accountId, $id);
        $this->audit->record(AuditAction::SESSION_REVOKED, $accountId, ['count' => 1]);

        return redirect('/settings/devices')->with('sessionsRevoked', 1);
    }

    /**
     * いま使っている端末以外をすべて切る。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function revokeOtherSessions(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $count = $this->sessions->revokeOthers($accountId, $request->session()->getId());
        $this->audit->record(AuditAction::SESSION_REVOKED, $accountId, ['count' => $count]);

        return redirect('/settings/devices')->with('sessionsRevoked', $count);
    }

    /**
     * 端末の信頼を取り消す。
     *
     * いま使っている端末だった場合はクッキーも落とす。残しても次から通らないが、
     * 意味のない値を持たせ続ける理由がない。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function revokeTrusted(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate(['id' => ['required', 'string']]);

        $current = $this->isCurrentDevice($accountId, $request, $request->string('id')->toString());
        $this->trustedDevices->revoke($accountId, $request->string('id')->toString());
        $this->audit->record(AuditAction::DEVICE_TRUST_REVOKED, $accountId, ['count' => 1]);

        $response = redirect('/settings/devices')->with('trustRevoked', true);

        return $current ? $response->withCookie(cookie()->forget(TrustedDevices::COOKIE)) : $response;
    }

    /**
     * すべての端末の信頼を取り消す。次のログインから全員が2段階目を求められる。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function revokeAllTrusted(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $count = $this->trustedDevices->revokeAll($accountId);
        $this->audit->record(AuditAction::DEVICE_TRUST_REVOKED, $accountId, ['count' => $count]);

        return redirect('/settings/devices')
            ->with('trustRevoked', true)
            ->withCookie(cookie()->forget(TrustedDevices::COOKIE));
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param Request $request
     * @param string $deviceId 取り消そうとしている端末のID
     * @return bool いま使っている端末か
     */
    private function isCurrentDevice(string $accountId, Request $request, string $deviceId): bool {
        foreach ($this->trustedDevices->listFor($accountId, $this->trustedDevices->tokenFrom($request)) as $device) {
            if ($device['id'] === $deviceId) return $device['isCurrent'];
        }

        return false;
    }
}
