<?php
namespace App\Modules\Credential\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Credential\Application\EnableMagicLink;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\GenerateRecoveryCodes;
use App\Modules\Credential\Application\ListCredentials;
use App\Modules\Credential\Application\RemoveCredential;
use App\Modules\Credential\Application\RenameCredential;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * 認証方法の管理画面
 */
class SecurityController {
    /** 有効化を確認するまで秘密鍵をセッションに置く */
    private const PENDING_TOTP = 'security.pending_totp';

    public function __construct(
        private readonly ChreeSession $session,
        private readonly EnableTotp $enableTotp,
        private readonly Totp $totp,
        private readonly GenerateRecoveryCodes $recoveryCodes,
        private readonly RemoveCredential $remove,
        private readonly EnableMagicLink $enableMagicLink,
        private readonly ListCredentials $credentials,
        private readonly CredentialRepository $repository,
        private readonly RenameCredential $rename,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function show(Request $request): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        return Inertia::render('Settings/Security', [
            'credentials' => $this->credentials->execute($accountId),
            'hasPassword' => $this->repository->has($accountId, CredentialType::PASSWORD),
            'recoveryCodeCount' => $this->recoveryCodes->remaining($accountId),
            'pendingTotp' => $request->session()->get(self::PENDING_TOTP . '.uri'),
        ]);
    }

    /**
     * メールでログインできるようにする。
     *
     * 有効化の行が無いとトークンを発行できない (MagicLinkVerifier 参照)。
     *
     * @return RedirectResponse
     */
    public function enableMagicLink(): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $this->enableMagicLink->execute($accountId);
        $this->audit->record(AuditAction::CREDENTIAL_ADDED, $accountId, ['type' => 'magic_link']);

        return redirect('/settings/security');
    }

    /**
     * 秘密鍵を発行して QR を出す。ここではまだ有効化しない
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function startTotp(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $secret = $this->enableTotp->generateSecret();
        $issuer = config('chreeid.issuer');
        $label = parse_url(is_string($issuer) ? $issuer : '', PHP_URL_HOST);

        $request->session()->put(self::PENDING_TOTP, [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $accountId, is_string($label) ? $label : 'ChreeID'),
        ]);

        return redirect('/settings/security');
    }

    /**
     * 認証アプリが出したコードで確認して有効化する
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException コードが合わない場合
     */
    public function confirmTotp(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $request->validate(['code' => ['required', 'string']]);

        $pending = $request->session()->get(self::PENDING_TOTP);
        $secret = is_array($pending) ? ($pending['secret'] ?? null) : null;
        if (!is_string($secret)) {
            throw ValidationException::withMessages(['code' => __('settings.totp.not_started')]);
        }

        try {
            $codes = $this->enableTotp->execute($accountId, $secret, $request->string('code')->toString());
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['code' => __('settings.totp.invalid_code')]);
        }

        $request->session()->forget(self::PENDING_TOTP);
        $this->audit->record(AuditAction::CREDENTIAL_ADDED, $accountId, ['type' => 'totp']);

        // 有効化と同時に発行している。平文を見せられるのはこの1回だけなので、
        // 控えずに端末を失うと詰む旨は画面側で伝える
        return redirect('/settings/security')->with('recoveryCodes', $codes);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function generateRecoveryCodes(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $codes = $this->recoveryCodes->execute($accountId);
        $this->audit->record(AuditAction::RECOVERY_CODES_GENERATED, $accountId);

        // 平文を見せられるのはこの1回だけなので、画面に持っていく
        return redirect('/settings/security')->with('recoveryCodes', $codes);
    }

    /**
     * 認証手段を1件だけ消す。
     *
     * パスキーや外部アカウントは同じ種別を複数持てるので、種別ではなく行を指す。
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException 最後の1件を消そうとした場合
     */
    public function removeCredential(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $request->validate(['id' => ['required', 'string']]);

        try {
            $this->remove->executeById($accountId, $request->string('id')->toString());
            $this->audit->record(AuditAction::CREDENTIAL_REMOVED, $accountId, [
                'credential' => $request->string('id')->toString(),
            ]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['credential' => $e->getMessage()]);
        }

        return redirect('/settings/security');
    }

    /**
     * パスキーの名前を変える。
     *
     * 同じ種別を複数持てるので、どれがどの端末か分からなくなる。
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException 名前を付けられない認証手段だった場合
     */
    public function renameCredential(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $request->validate([
            'id' => ['required', 'string'],
            'label' => ['required', 'string', 'max:60'],
        ]);

        try {
            $this->rename->execute(
                $accountId,
                $request->string('id')->toString(),
                $request->string('label')->toString(),
            );
            $this->audit->record(AuditAction::CREDENTIAL_RENAMED, $accountId, [
                'label' => $request->string('label')->toString(),
            ]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['credential' => $e->getMessage()]);
        }

        return redirect('/settings/security');
    }

    /**
     * マジックリンクでのログインをやめる。
     *
     * 有効化の行は1つしか無いので、こちらは種別で消す。
     *
     * @return RedirectResponse
     * @throws ValidationException 最後の1件を消そうとした場合
     */
    public function disableMagicLink(): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        try {
            $this->remove->execute($accountId, CredentialType::MAGIC_LINK);
            $this->audit->record(AuditAction::CREDENTIAL_REMOVED, $accountId, ['type' => 'magic_link']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['credential' => $e->getMessage()]);
        }

        return redirect('/settings/security');
    }
}
