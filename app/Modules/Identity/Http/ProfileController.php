<?php
namespace App\Modules\Identity\Http;

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
    ) {}

    /**
     * @return Response|RedirectResponse
     */
    public function show(): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $account = $this->accounts->findById($accountId);
        if ($account === null) return redirect('/login');

        return Inertia::render('Profile', [
            'displayName' => $account->displayName,
            'email' => $account->email,
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

        return redirect('/profile')->with('profileSaved', true);
    }
}
