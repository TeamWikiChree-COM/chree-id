<?php
namespace App\Modules\Identity\Http;

use App\Modules\Identity\Application\CompleteRegistration;
use App\Modules\Identity\Application\RegistrationTokenException;
use App\Modules\Identity\Application\StartRegistration;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Support\Turnstile\TurnstileGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ユーザーアカウントの登録。
 *
 * 確認メールのリンクを踏むまでアカウントは作られない。
 * そのため store() の応答はアドレスの登録有無で変わらず、存在確認には使えない。
 */
class RegisterController {
    /** 理由ごとの画面表示。内部の理由コードをそのまま出さないための対応表 */
    private const FAILURE_MESSAGES = [
        RegistrationTokenException::NOT_FOUND => 'このリンクは使えません。お手数ですが、もう一度登録をやり直してください',
        RegistrationTokenException::EXPIRED => 'このリンクは期限切れです。お手数ですが、もう一度登録をやり直してください',
        RegistrationTokenException::EMAIL_TAKEN => 'このメールアドレスは既に使われています。ログインをお試しください',
    ];

    public function __construct(
        private readonly StartRegistration $startRegistration,
        private readonly CompleteRegistration $completeRegistration,
        private readonly ChreeSession $session,
    ) {}

    /**
     * @return Response
     */
    public function show(): Response {
        return Inertia::render('Auth/Register');
    }

    /**
     * 申し込みを受け付けて確認メールを送る。
     *
     * @param Request $request
     * @param TurnstileGuard $turnstile
     * @return RedirectResponse
     * @throws ValidationException 入力または Turnstile の検証に失敗した場合
     */
    public function store(Request $request, TurnstileGuard $turnstile): RedirectResponse {
        $turnstile->check($request);

        $validated = $this->validateInput($request);

        $this->startRegistration->execute(
            $validated['email'],
            $validated['display_name'],
            $validated['password'],
        );

        return redirect('/register/sent')->with('registeredEmail', $validated['email']);
    }

    /**
     * 確認メールを送った旨の画面。
     *
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function sent(Request $request): Response|RedirectResponse {
        $email = $request->session()->get('registeredEmail');
        if (!is_string($email)) return redirect('/register');

        return Inertia::render('Auth/RegisterSent', ['email' => $email]);
    }

    /**
     * 確認リンクを受けてアカウントを作り、そのままログインさせる。
     *
     * @param string $token メールに載せた平文トークン
     * @return Response|RedirectResponse
     */
    public function verify(string $token): Response|RedirectResponse {
        try {
            $account = $this->completeRegistration->execute($token);
        } catch (RegistrationTokenException $e) {
            return Inertia::render('Auth/RegisterFailed', [
                'message' => self::FAILURE_MESSAGES[$e->reason],
            ]);
        }

        $this->session->login($account->id);

        return redirect('/');
    }

    /**
     * @param Request $request
     * @return array{email: string, display_name: string|null, password: string}
     * @throws ValidationException
     */
    private function validateInput(Request $request): array {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            // 表示名は任意。DB も domain も null を許しており、Google 連携でも空のことがある
            'display_name' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $displayName = $request->string('display_name')->trim()->toString();

        return [
            'email' => $request->string('email')->toString(),
            'display_name' => $displayName === '' ? null : $displayName,
            'password' => $request->string('password')->toString(),
        ];
    }
}
