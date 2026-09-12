<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\Verifiers\PasswordVerifier;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * 設定画面からのパスワード変更。
 *
 * 再設定 (PasswordResetController) はメールのトークンが本人の裏付けだが、
 * こちらは**いまのパスワードを知っていること**が裏付けになる。
 * まだ持っていないアカウントは、ログイン中であること自体を裏付けとして新規設定する。
 */
class PasswordController {
    private readonly ChreeSession $session;
    private readonly CredentialRepository $credentials;
    private readonly PasswordVerifier $verifier;
    private readonly SetPassword $setPassword;

    public function __construct(ChreeSession $session, CredentialRepository $credentials, PasswordVerifier $verifier, SetPassword $setPassword) {
        $this->session = $session;
        $this->credentials = $credentials;
        $this->verifier = $verifier;
        $this->setPassword = $setPassword;
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException 現在のパスワードが違う、または新しいものが短い場合
     */
    public function update(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate([
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        if ($this->credentials->has($accountId, CredentialType::PASSWORD)) {
            $this->assertCurrentPassword($accountId, $request->string('current_password')->toString());
        }

        try {
            $this->setPassword->execute($accountId, $request->string('password')->toString());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        return redirect('/settings/security')->with('passwordChanged', true);
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $current 入力された現在のパスワード
     * @return void
     * @throws ValidationException 一致しない場合
     */
    private function assertCurrentPassword(string $accountId, string $current): void {
        if ($this->verifier->verifyPassword($accountId, $current)->isSuccess()) return;

        throw ValidationException::withMessages(['current_password' => '現在のパスワードが違います']);
    }
}
