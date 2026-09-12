<?php
namespace App\Modules\Linking\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\RevokeServiceAccess;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;

/**
 * 利用者自身が、連携しているサービスを切る。
 *
 * 管理画面の「接続サービス」(サービスそのものの登録) とは別物。
 * こちらは自分のアカウントとサービスの結び付きだけを扱う。
 */
class ConnectedServiceController {
    public function __construct(
        private readonly ChreeSession $session,
        private readonly RevokeServiceAccess $revoke,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param string $client サービスの client_id
     * @return RedirectResponse
     */
    public function destroy(string $client): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $this->revoke->execute($accountId, $client);
        $this->audit->record(AuditAction::SERVICE_REVOKED, $accountId, ['client' => $client]);

        return redirect('/')->with('serviceRevoked', true);
    }
}
