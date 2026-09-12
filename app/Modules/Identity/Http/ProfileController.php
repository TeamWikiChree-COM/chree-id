<?php
namespace App\Modules\Identity\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Application\AccountIcons;
use App\Modules\Identity\Application\ConfirmEmailChange;
use App\Modules\Identity\Application\ConfirmEmailVerification;
use App\Modules\Identity\Application\RequestEmailChange;
use App\Modules\Identity\Application\RequestEmailVerification;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Domain\EmailChangeResult;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Identity\Infrastructure\PendingEmailChangeModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * プロフィールの編集。
 *
 * 表示名・メールアドレス・アイコンを扱う。アイコンの中身そのものは AccountIcons、
 * 画像の出し入れは IconController が持つ。
 */
class ProfileController {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly ChreeSession $session,
        private readonly RequestEmailVerification $requestVerification,
        private readonly ConfirmEmailVerification $confirmVerification,
        private readonly RequestEmailChange $requestChange,
        private readonly ConfirmEmailChange $confirmChange,
        private readonly AccountIcons $icons,
        private readonly AuditLog $audit,
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
            'iconSource' => $account->iconSource->value,
            'iconUrl' => $this->icons->urlFor($account),
            'pendingEmail' => $this->pendingEmailChange($accountId),
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
        $this->audit->record(AuditAction::PROFILE_UPDATED, $accountId);

        return redirect('/settings')->with('profileSaved', true);
    }

    /**
     * 確認待ちのメールアドレス変更。
     *
     * 出さないと、送ったきり届かなかったときに本人が何も分からない。
     *
     * @param string $accountId アカウントID (ULID)
     * @return array{email: string, expiresAt: string}|null 申し込みが無ければ null
     */
    private function pendingEmailChange(string $accountId): ?array {
        $pending = PendingEmailChangeModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        if ($pending === null) return null;

        return ['email' => $pending->new_email, 'expiresAt' => $pending->expires_at->toDateTimeString()];
    }

    /**
     * 確認待ちの変更を取り消す。
     *
     * 打ち間違えたまま期限切れを待たせない。届いたリンクもこれで無効になる。
     *
     * @return RedirectResponse
     */
    public function cancelEmailChange(): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        PendingEmailChangeModel::query()->where('auth_identity_id', $accountId)->delete();
        $this->audit->record(AuditAction::EMAIL_CHANGE_CANCELLED, $accountId);

        return redirect('/settings')->with('emailChangeCancelled', true);
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

        $result = $this->requestChange->execute($accountId, $request->string('email')->toString());

        // 断る理由で直しかたが違うので、同じ文言に潰さない
        $message = match ($result) {
            EmailChangeResult::SENT => null,
            EmailChangeResult::SAME_AS_CURRENT => __('settings.profile.email_same_as_current'),
            EmailChangeResult::TAKEN => __('settings.profile.email_unavailable'),
            EmailChangeResult::UNKNOWN_ACCOUNT => __('settings.profile.account_not_found'),
        };

        if ($message !== null) throw ValidationException::withMessages(['email' => $message]);

        $this->audit->record(AuditAction::EMAIL_CHANGE_REQUESTED, $accountId, [
            'email' => $request->string('email')->toString(),
        ]);

        return redirect('/settings')->with('emailChangeSent', true);
    }

    /**
     * 変更確認リンクの着地。
     *
     * @param string $token メールに載せた平文トークン
     * @return RedirectResponse
     */
    public function confirmEmailChange(string $token): RedirectResponse {
        $accountId = $this->confirmChange->execute($token);
        $changed = $accountId !== null;

        if ($accountId !== null) $this->audit->record(AuditAction::EMAIL_CHANGED, $accountId);

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
        $accountId = $this->confirmVerification->execute($token);
        $verified = $accountId !== null;

        if ($accountId !== null) $this->audit->record(AuditAction::EMAIL_VERIFIED, $accountId);

        return redirect($this->session->isLoggedIn() ? '/settings' : '/login')
            ->with('emailVerified', $verified);
    }
}
