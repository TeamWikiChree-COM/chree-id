<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
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
     * @return RedirectResponse
     * @throws ValidationException 認証できなかった場合
     */
    public function store(Request $request): RedirectResponse {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $accountId = $this->authenticate(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        // アカウントの有無を出し分けると、総当たりで登録済みかを調べられてしまう
        if ($accountId === null) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが違います',
            ]);
        }

        $this->session->login($accountId);

        return redirect()->intended('/');
    }

    /**
     * @return RedirectResponse
     */
    public function destroy(): RedirectResponse {
        $this->session->logout();

        return redirect('/login');
    }

    /**
     * @param string $email メールアドレス
     * @param string $password パスワード
     * @return string|null 認証できたアカウントID
     */
    private function authenticate(string $email, string $password): ?string {
        $account = $this->accounts->findByEmail($email);
        if ($account === null || $account->isSuspended()) return null;

        $factors = new VerifiedFactors();
        $this->verify->execute($account->id, CredentialType::PASSWORD, ['password' => $password], $factors);

        return $this->complete->execute($account->id, $factors) ? $account->id : null;
    }
}
