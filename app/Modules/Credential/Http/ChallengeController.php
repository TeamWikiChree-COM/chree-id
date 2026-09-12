<?php
namespace App\Modules\Credential\Http;

use App\Modules\Audit\Application\LoginHistory;
use App\Modules\Audit\Domain\LoginMethod;
use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\PendingAuthentication;
use App\Modules\Device\Application\TrustedDevices;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * 二要素目の入力。
 *
 * 一次認証を通っただけでは PendingAuthentication に載るだけで、
 * ここを通って AuthenticationPolicy が満たされて初めてログインになる。
 */
class ChallengeController {
    public function __construct(
        private readonly PendingAuthentication $pending,
        private readonly VerifyCredential $verify,
        private readonly CompleteAuthentication $complete,
        private readonly CredentialRepository $credentials,
        private readonly ChreeSession $session,
        private readonly TrustedDevices $trustedDevices,
        private readonly LoginHistory $history,
    ) {}

    /**
     * @return Response|RedirectResponse 待機中でなければログイン画面へ戻す
     */
    public function show(): Response|RedirectResponse {
        $accountId = $this->pending->accountId();
        if ($accountId === null) return redirect('/login');

        return Inertia::render('Auth/Challenge', [
            'hasRecoveryCodes' => $this->credentials->has($accountId, CredentialType::RECOVERY_CODE),
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException コードが違う場合
     */
    public function store(Request $request): RedirectResponse {
        $accountId = $this->pending->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate([
            'code' => ['required', 'string'],
            'useRecoveryCode' => ['boolean'],
            'trustDevice' => ['boolean'],
        ]);

        $type = $request->boolean('useRecoveryCode') ? CredentialType::RECOVERY_CODE : CredentialType::TOTP;
        $factors = $this->pending->factors();

        $this->verify->execute($accountId, $type, ['code' => $request->string('code')->toString()], $factors);

        if (!$this->complete->execute($accountId, $factors)) {
            $this->history->record($accountId, $type->value, succeeded: false);

            throw ValidationException::withMessages(['code' => __('auth.challenge.invalid_code')]);
        }

        $this->pending->forget();
        $this->session->login($accountId, $type->value);

        $response = redirect()->intended('/');

        // 2段階目を通した直後だけ信頼できる。ここ以外で配ってはいけない
        if (!$request->boolean('trustDevice')) return $response;

        return $response->withCookie($this->trustCookie($request, $accountId));
    }

    /**
     * 信頼済み端末のクッキーを組み立てる。
     *
     * JS からは触らせない (httpOnly)。持ち出されると2段階目を飛ばせてしまう。
     *
     * @param Request $request
     * @param string $accountId アカウントID (ULID)
     * @return Cookie
     */
    private function trustCookie(Request $request, string $accountId): Cookie {
        $token = $this->trustedDevices->remember($request, $accountId);

        return cookie(
            TrustedDevices::COOKIE,
            $token,
            TrustedDevices::LIFETIME_DAYS * 24 * 60,
            httpOnly: true,
        );
    }

    /**
     * 二要素目の入力をやめてログイン画面に戻る。
     *
     * @return RedirectResponse
     */
    public function destroy(): RedirectResponse {
        $this->pending->forget();

        return redirect('/login');
    }
}
