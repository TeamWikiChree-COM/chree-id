<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\PendingAuthentication;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
        ]);

        $type = $request->boolean('useRecoveryCode') ? CredentialType::RECOVERY_CODE : CredentialType::TOTP;
        $factors = $this->pending->factors();

        $this->verify->execute($accountId, $type, ['code' => $request->string('code')->toString()], $factors);

        if (!$this->complete->execute($accountId, $factors)) {
            throw ValidationException::withMessages(['code' => 'コードが正しくありません']);
        }

        $this->pending->forget();
        $this->session->login($accountId);

        return redirect()->intended('/');
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
