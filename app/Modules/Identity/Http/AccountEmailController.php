<?php
namespace App\Modules\Identity\Http;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Application\AccountEmailException;
use App\Modules\Identity\Application\AccountEmails;
use App\Modules\Identity\Application\AddAccountEmail;
use App\Modules\Identity\Application\ConfirmAccountEmail;
use App\Modules\Identity\Application\PromoteAccountEmail;
use App\Modules\Identity\Application\ChreeSession;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 追加のメールアドレスの登録・確認・削除・主アドレスへの切り替え。
 *
 * 追加アドレスはサービスへ渡すための選択肢で、ログインやパスワード再設定には使わない。
 */
class AccountEmailController extends Controller {
    private readonly ChreeSession $session;
    private readonly AddAccountEmail $add;
    private readonly ConfirmAccountEmail $confirm;
    private readonly AccountEmails $emails;
    private readonly PromoteAccountEmail $promote;
    private readonly AuditLog $audit;

    public function __construct(
        ChreeSession $session,
        AddAccountEmail $add,
        ConfirmAccountEmail $confirm,
        AccountEmails $emails,
        PromoteAccountEmail $promote,
        AuditLog $audit,
    ) {
        $this->session = $session;
        $this->add = $add;
        $this->confirm = $confirm;
        $this->emails = $emails;
        $this->promote = $promote;
        $this->audit = $audit;
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $email = $request->string('email')->trim()->toString();

        $this->attempt('email', fn () => $this->add->execute($accountId, $email));
        $this->audit->record(AuditAction::EMAIL_ADDED, $accountId, ['email' => $email]);

        return redirect('/settings')->with('accountEmail', 'added');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function resend(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $this->attempt('id', fn () => $this->add->resend($accountId, $request->string('id')->toString()));

        return redirect('/settings')->with('accountEmail', 'resent');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function destroy(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $email = $this->attempt('id', fn (): string => $this->emails->remove($accountId, $request->string('id')->toString()));
        $this->audit->record(AuditAction::EMAIL_REMOVED, $accountId, ['email' => $email]);

        return redirect('/settings')->with('accountEmail', 'removed');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function makePrimary(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $email = $this->attempt('id', fn (): string => $this->promote->execute($accountId, $request->string('id')->toString()));
        $this->audit->record(AuditAction::EMAIL_CHANGED, $accountId, ['email' => $email]);

        return redirect('/settings')->with('accountEmail', 'promoted');
    }

    /**
     * 確認リンクの着地。ログインを要求しない (別の端末でメールを開くことがあるため)。
     *
     * @param string $token メールに載せた平文トークン
     * @return RedirectResponse
     */
    public function verify(string $token): RedirectResponse {
        $accountId = $this->confirm->execute($token);
        if ($accountId !== null) $this->audit->record(AuditAction::EMAIL_VERIFIED, $accountId);

        return redirect($this->session->isLoggedIn() ? '/settings' : '/login')
            ->with('accountEmail', $accountId !== null ? 'verified' : 'verify_failed');
    }

    /**
     * 断る理由ごとに文言を分けて、入力欄のエラーとして返す。
     *
     * @template T
     * @param string $field エラーを出す入力欄
     * @param callable(): T $action
     * @return T
     * @throws ValidationException
     */
    private function attempt(string $field, callable $action): mixed {
        try {
            return $action();
        } catch (AccountEmailException $e) {
            throw ValidationException::withMessages([$field => __("settings.emails.error.{$e->reason}")]);
        }
    }
}
