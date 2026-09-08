<?php
namespace App\Modules\Identity\Http;

use App\Modules\Identity\Application\ConfirmEmailChange;
use App\Modules\Identity\Application\ConfirmEmailVerification;
use App\Modules\Identity\Application\RequestEmailChange;
use App\Modules\Identity\Application\RequestEmailVerification;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * プロフィールの編集。
 *
 * 今は表示名だけ。メールアドレスの変更は再検証が要るので、ここには入れていない。
 */
class ProfileController {
    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly ChreeSession $session,
        private readonly RequestEmailVerification $requestVerification,
        private readonly ConfirmEmailVerification $confirmVerification,
        private readonly RequestEmailChange $requestChange,
        private readonly ConfirmEmailChange $confirmChange,
    ) {}

    /**
     * @return Response|RedirectResponse
     */
    public function show(): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $account = $this->accounts->findById($accountId);
        if ($account === null) return redirect('/login');

        return Inertia::render('Settings/Profile', [
            'displayName' => $account->displayName,
            'email' => $account->email,
            'emailVerified' => $account->isEmailVerified(),
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function update(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate([
            'display_name' => ['nullable', 'string', 'max:100'],
        ]);

        $displayName = $request->string('display_name')->trim()->toString();
        $this->accounts->updateDisplayName($accountId, $displayName === '' ? null : $displayName);

        return redirect('/settings')->with('profileSaved', true);
    }

    /**
     * メールアドレス確認のリンクを送る。
     *
     * @return RedirectResponse
     */
    public function sendEmailVerification(): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $this->requestVerification->execute($accountId);

        return redirect('/settings')->with('verificationSent', true);
    }

    /**
     * メールアドレスの変更を申し込む。
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function changeEmail(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $accepted = $this->requestChange->execute($accountId, $request->string('email')->toString());

        if (!$accepted) {
            throw ValidationException::withMessages([
                'email' => 'このメールアドレスは使えません',
            ]);
        }

        return redirect('/settings')->with('emailChangeSent', true);
    }

    /**
     * 変更確認リンクの着地。
     *
     * @param string $token メールに載せた平文トークン
     * @return RedirectResponse
     */
    public function confirmEmailChange(string $token): RedirectResponse {
        $changed = $this->confirmChange->execute($token) !== null;

        return redirect($this->session->isLoggedIn() ? '/settings' : '/login')
            ->with('emailChanged', $changed);
    }

    /**
     * 確認リンクの着地。
     *
     * ログインを要求しない。別の端末でメールを開くことがあるため。
     * トークン自体が本人であることの証明になっている。
     *
     * @param string $token メールに載せた平文トークン
     * @return RedirectResponse
     */
    public function confirmEmail(string $token): RedirectResponse {
        $verified = $this->confirmVerification->execute($token) !== null;

        return redirect($this->session->isLoggedIn() ? '/settings' : '/login')
            ->with('emailVerified', $verified);
    }
}
