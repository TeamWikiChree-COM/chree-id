<?php
namespace App\Modules\Identity\Http;

use App\Modules\Audit\Domain\LoginMethod;
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
 * 申し込みで受け取るのはメールアドレスだけで、パスワードは確認リンクを開いたあとに決める。
 * 確認メールのリンクを踏むまでアカウントは作られないので、
 * store() の応答はアドレスの登録有無で変わらず、存在確認には使えない。
 */
class RegisterController {
    /**
     * 理由ごとの画面表示。内部の理由コードをそのまま出さないための対応表
     *
     * @return array<string, string>
     */
    private function failureMessages(): array {
        return [
            RegistrationTokenException::NOT_FOUND => __('auth.register.link_invalid'),
            RegistrationTokenException::EXPIRED => __('auth.register.link_expired'),
            RegistrationTokenException::EMAIL_TAKEN => __('auth.register.email_taken'),
        ];
    }

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

        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $email = $request->string('email')->toString();
        $this->startRegistration->execute($email);

        return redirect('/register/sent')->with('registeredEmail', $email);
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
     * 確認リンクの着地。ここでパスワードを決めてもらう。
     *
     * @param string $token メールに載せた平文トークン
     * @return Response
     */
    public function verify(string $token): Response {
        if (!$this->completeRegistration->isUsable($token)) {
            return Inertia::render('Auth/RegisterFailed', [
                'message' => $this->failureMessages()[RegistrationTokenException::EXPIRED],
            ]);
        }

        return Inertia::render('Auth/RegisterPassword', ['token' => $token]);
    }

    /**
     * パスワードを受け取ってアカウントを作り、そのままログインさせる。
     *
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws ValidationException
     */
    public function complete(Request $request): RedirectResponse|Response {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
            'display_name' => ['nullable', 'string', 'max:100'],
        ]);

        $displayName = $request->string('display_name')->trim()->toString();

        try {
            $account = $this->completeRegistration->execute(
                $request->string('token')->toString(),
                $request->string('password')->toString(),
                $displayName === '' ? null : $displayName,
            );
        } catch (RegistrationTokenException $e) {
            return Inertia::render('Auth/RegisterFailed', [
                'message' => $this->failureMessages()[$e->reason],
            ]);
        }

        $this->session->login($account->id, LoginMethod::REGISTRATION->value);

        return redirect('/');
    }
}
