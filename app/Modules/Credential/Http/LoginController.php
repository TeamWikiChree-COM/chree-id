<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Credential\Infrastructure\PendingAuthentication;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
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
        private readonly ChreeAccountRepository $accounts,
        private readonly VerifyCredential $verify,
        private readonly CompleteAuthentication $complete,
        private readonly PendingAuthentication $pending,
        private readonly ChreeSession $session,
    ) {}

    /**
     * @return Response
     */
    public function show(): Response {
        return Inertia::render('Auth/Login');
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

        $account = $this->accounts->findByEmail($request->string('email')->toString());
        if ($account === null || $account->isSuspended()) throw $this->invalidCredentials();

        $factors = new VerifiedFactors();
        $verified = $this->verify->execute(
            $account->id,
            CredentialType::PASSWORD,
            ['password' => $request->string('password')->toString()],
            $factors,
        );

        if (!$verified->isSuccess()) throw $this->invalidCredentials();

        // 2FA を有効にしているアカウントは、パスワードだけでは成立しない
        if (!$this->complete->execute($account->id, $factors)) {
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
     * @return RedirectResponse
     */
    public function destroy(): RedirectResponse {
        $this->pending->forget();
        $this->session->logout();

        return redirect('/login');
    }

}
