<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\EnableMagicLink;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\GenerateRecoveryCodes;
use App\Modules\Credential\Application\RemoveCredential;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Infrastructure\ChreeSession;
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
    ) {}

    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function show(Request $request): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        return Inertia::render('Security', [
            'credentials' => $this->credentialsOf($accountId),
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
        if ($accountId === null) return redirect('/login');

        $this->enableMagicLink->execute($accountId);

        return redirect('/security');
    }

    /**
     * 秘密鍵を発行して QR を出す。ここではまだ有効化しない
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function startTotp(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $secret = $this->enableTotp->generateSecret();
        $issuer = config('chreeid.issuer');
        $label = parse_url(is_string($issuer) ? $issuer : '', PHP_URL_HOST);

        $request->session()->put(self::PENDING_TOTP, [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $accountId, is_string($label) ? $label : 'ChreeID'),
        ]);

        return redirect('/security');
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
        if ($accountId === null) return redirect('/login');

        $request->validate(['code' => ['required', 'string']]);

        $pending = $request->session()->get(self::PENDING_TOTP);
        $secret = is_array($pending) ? ($pending['secret'] ?? null) : null;
        if (!is_string($secret)) {
            throw ValidationException::withMessages(['code' => '先に設定を開始してください']);
        }

        try {
            $this->enableTotp->execute($accountId, $secret, $request->string('code')->toString());
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['code' => 'コードが一致しません']);
        }

        $request->session()->forget(self::PENDING_TOTP);

        return redirect('/security');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function generateRecoveryCodes(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        // 平文を見せられるのはこの1回だけなので、画面に持っていく
        return redirect('/security')->with('recoveryCodes', $this->recoveryCodes->execute($accountId));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException 最後の1件を消そうとした場合
     */
    public function removeCredential(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate(['type' => ['required', 'string']]);

        $type = CredentialType::tryFrom($request->string('type')->toString());
        if ($type === null) throw ValidationException::withMessages(['type' => '不明な認証方法です']);

        try {
            $this->remove->execute($accountId, $type);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['type' => $e->getMessage()]);
        }

        return redirect('/security');
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<array{type: string, label: string|null, lastUsedAt: string|null}>
     */
    private function credentialsOf(string $accountId): array {
        $rows = CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', '!=', CredentialType::RECOVERY_CODE->value)
            ->orderBy('type')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $data = $row->data;
            $label = is_array($data) && is_string($data['label'] ?? null) ? $data['label'] : null;

            $result[] = [
                'type' => $row->type->value,
                'label' => $label,
                'lastUsedAt' => $row->last_used_at?->toDateTimeString(),
            ];
        }

        return $result;
    }
}
