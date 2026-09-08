<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\PasswordResetTokenException;
use App\Modules\Credential\Application\RequestPasswordReset;
use App\Modules\Credential\Application\ResetPassword;
use App\Support\Turnstile\TurnstileGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * パスワードの再設定。
 *
 * 送信の応答はアドレスの登録有無で変わらないので、存在確認には使えない。
 * 再設定してもログインはさせない。リンクを踏んだのが本人とは限らないため。
 */
class PasswordResetController {
    public function __construct(
        private readonly RequestPasswordReset $requestReset,
        private readonly ResetPassword $reset,
    ) {}

    /**
     * @return Response
     */
    public function show(): Response {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * @param Request $request
     * @param TurnstileGuard $turnstile
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request, TurnstileGuard $turnstile): RedirectResponse {
        $turnstile->check($request);

        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $email = $request->string('email')->toString();
        $this->requestReset->execute($email);

        return redirect('/password/forgot/sent')->with('resetEmail', $email);
    }

    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function sent(Request $request): Response|RedirectResponse {
        $email = $request->session()->get('resetEmail');
        if (!is_string($email)) return redirect('/password/forgot');

        return Inertia::render('Auth/ForgotPasswordSent', ['email' => $email]);
    }

    /**
     * 新しいパスワードの入力画面。
     *
     * パスワードを打たせてから期限切れを伝えるのは無駄なので、先に確かめる。
     *
     * @param string $token メールに載せた平文トークン
     * @return Response
     */
    public function edit(string $token): Response {
        if (!$this->reset->isUsable($token)) {
            return Inertia::render('Auth/ResetPasswordFailed');
        }

        return Inertia::render('Auth/ResetPassword', ['token' => $token]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws ValidationException
     */
    public function update(Request $request): RedirectResponse|Response {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $this->reset->execute(
                $request->string('token')->toString(),
                $request->string('password')->toString(),
            );
        } catch (PasswordResetTokenException) {
            return Inertia::render('Auth/ResetPasswordFailed');
        }

        // ここでログインさせない。リンクを開いたのが本人とは限らず、2FA も迂回してしまう
        return redirect('/login')->with('passwordReset', true);
    }
}
