<?php
namespace App\Modules\Identity\Http;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Application\AccountEmailException;
use App\Modules\Identity\Application\ServiceEmails;
use App\Modules\Identity\Application\ChreeSession;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 連携しているサービスへ渡すメールアドレスを選ぶ。
 */
class ServiceEmailController extends Controller {
    private readonly ChreeSession $session;
    private readonly ServiceEmails $serviceEmails;
    private readonly AuditLog $audit;

    public function __construct(ChreeSession $session, ServiceEmails $serviceEmails, AuditLog $audit) {
        $this->session = $session;
        $this->serviceEmails = $serviceEmails;
        $this->audit = $audit;
    }

    /**
     * @param Request $request
     * @param string $serviceAccount サービスアカウントのID (ULID)
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function update(Request $request, string $serviceAccount): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $request->validate(['email' => ['nullable', 'string', 'email', 'max:255']]);
        $email = $request->string('email')->trim()->toString();
        $email = $email === '' ? null : $email;

        try {
            $this->serviceEmails->assign($accountId, $serviceAccount, $email);
        } catch (AccountEmailException $e) {
            throw ValidationException::withMessages(['email' => __("settings.emails.error.{$e->reason}")]);
        }

        $this->audit->record(AuditAction::SERVICE_EMAIL_ASSIGNED, $accountId, [
            'service_account_id' => $serviceAccount,
            'email' => $email,
        ]);

        return redirect("/connected/{$serviceAccount}")->with('serviceEmailSaved', true);
    }
}
