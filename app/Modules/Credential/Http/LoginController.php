<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Credential\Infrastructure\PendingAuthentication;
use App\Modules\Device\Application\LoginSessions;
use App\Modules\Device\Application\TrustedDevices;
use App\Modules\Identity\Application\ResolveByEmail;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Provider\Infrastructure\LoginHint;
use App\Support\Turnstile\TurnstileGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * パスワードによるログイン
 */
class LoginController {
    public function __construct(
        private readonly ResolveByEmail $byEmail,
        private readonly VerifyCredential $verify,
        private readonly CompleteAuthentication $complete,
        private readonly PendingAuthentication $pending,
        private readonly ChreeSession $session,
        private readonly LoginHint $loginHint,
        private readonly TrustedDevices $trustedDevices,
        private readonly LoginSessions $sessions,
    ) {}

    /**
     * @return Response
     */
    public function show(): Response {
        return Inertia::render('Auth/Login', ['email' => $this->loginHint->pull()]);
    }

    /**
     * @param Request $request
     * @param TurnstileGuard $turnstile
     * @return RedirectResponse
     * @throws ValidationException 認証できなかった場合
     */
    public function store(Request $request, TurnstileGuard $turnstile): RedirectResponse {
        $turnstile->check($request);

        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // メールが指すのは UserAccount。サービスアカウントはサービス経由で入る
        $account = $this->byEmail->primary($request->string('email')->toString());
        if ($account === null) throw $this->invalidCredentials();

        $factors = new VerifiedFactors();
        $verified = $this->verify->execute(
            $account->id,
            CredentialType::PASSWORD,
            ['password' => $request->string('password')->toString()],
            $factors,
        );

        if (!$verified->isSuccess()) throw $this->invalidCredentials();

        // 2FA を有効にしているアカウントは、パスワードだけでは成立しない。
        // ただし本人が2段階目を通したうえで信頼した端末なら、そこは省く
        $trusted = $this->trustedDevices->isTrusted($account->id, $this->trustedDevices->tokenFrom($request));

        if (!$trusted && !$this->complete->execute($account->id, $factors)) {
            $this->pending->start($account->id, $factors);

            return redirect('/login/challenge');
        }

        $this->session->login($account->id);

        return redirect()->intended('/');
    }

    /**
     * アカウントの有無を出し分けると、総当たりで登録済みかを調べられてしまうので文言を揃える。
     *
     * @return ValidationException
     */
    private function invalidCredentials(): ValidationException {
        return ValidationException::withMessages([
            'email' => 'メールアドレスまたはパスワードが違います',
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function destroy(Request $request): RedirectResponse {
        // セッションIDが再生成される前に控えを落とす。後だと別のIDを消しにいく
        $this->sessions->forget($request->session()->getId());

        $this->pending->forget();
        $this->session->logout();

        return redirect('/login');
    }

}
