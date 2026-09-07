<?php
namespace App\Modules\Identity\Http;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ユーザーアカウントの登録
 */
class RegisterController {
    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly SetPassword $setPassword,
        private readonly ChreeSession $session,
    ) {}

    /**
     * @return Response
     */
    public function show(): Response {
        return Inertia::render('Auth/Register');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'display_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $email = $request->string('email')->toString();

        // TODO: メール検証を入れたら、既存かどうかを画面に出さず「確認メールを送った」に統一する
        if ($this->accounts->findByEmail($email) !== null) {
            throw ValidationException::withMessages([
                'email' => 'このメールアドレスは既に使われています',
            ]);
        }

        $account = $this->accounts->create(
            AccountOrigin::USER,
            $email,
            $request->string('display_name')->toString(),
        );

        $this->setPassword->execute($account->id, $request->string('password')->toString());
        $this->session->login($account->id);

        return redirect('/');
    }
}
